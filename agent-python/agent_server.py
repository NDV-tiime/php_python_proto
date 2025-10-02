import asyncio
import json
import uuid
from typing import Any, Dict

import aiohttp
from aiohttp import web

class JsonRpcClient:
    """JSON-RPC 2.0 client for communicating with browser extension."""

    def __init__(self, websocket: web.WebSocketResponse):
        self.pending_requests: Dict[str, asyncio.Future] = {}
        self.websocket = websocket

    async def call_method(
        self, method: str, params: Any = None, timeout: float = 5.0
    ) -> Any:
        """
        Call a JSON-RPC method on the browser extension.

        Args:
            method: The method name to call
            params: Parameters to pass to the method
            timeout: Timeout in seconds

        Returns:
            The result from the method call

        Raises:
            RuntimeError: If no WebSocket connection is available
            asyncio.TimeoutError: If the request times out
            Exception: If the RPC call returns an error
        """
        if self.websocket.closed:
            raise RuntimeError("No WebSocket connection available")

        request_id = str(uuid.uuid4())

        # Create JSON-RPC request
        rpc_request = {
            "jsonrpc": "2.0",
            "method": method,
            "params": params,
            "id": request_id,
        }

        # Wrap in WebSocket message format expected by extension
        ws_message = {"type": "rpc_call", "id": request_id, "data": rpc_request}

        # Create future for response
        loop = asyncio.get_running_loop()
        future = loop.create_future()
        self.pending_requests[request_id] = future

        try:
            # Send request
            if self.websocket.closed:
                raise RuntimeError("WebSocket connection closed")

            print(f"[Python] Sending RPC: {json.dumps(rpc_request)}")
            await self.websocket.send_json(ws_message)

            # Wait for response with timeout
            result = await asyncio.wait_for(future, timeout=timeout)
            return result

        except Exception:
            # Clean up pending request
            self.pending_requests.pop(request_id, None)
            raise

    async def handle_message(self, message: Dict[str, Any]):
        """
        Handle incoming WebSocket message.

        Args:
            message: The parsed WebSocket message
        """
        if message.get("type") == "rpc_response":
            request_id = message.get("id")
            rpc_response = message.get("data")

            print(f"[Python] Received RPC response: {json.dumps(rpc_response)}")

            if request_id in self.pending_requests:
                future = self.pending_requests.pop(request_id)

                if not future.done():
                    if rpc_response is None:
                        future.set_exception(Exception("RPC Error: Received null response"))
                    elif "error" in rpc_response:
                        error = rpc_response["error"]
                        future.set_exception(
                            Exception(
                                f"RPC Error {error.get('code')}: {error.get('message')}"
                            )
                        )
                    else:
                        future.set_result(rpc_response.get("result"))
        else:
            print(f"[Python] Received non-RPC response message: {message}")

    async def message_loop(self):
        """
        Handle incoming WebSocket messages
        """
        try:
            async for msg in self.websocket:
                if msg.type == aiohttp.WSMsgType.TEXT:
                    try:
                        data = json.loads(msg.data)
                        await self.handle_message(data)
                    except json.JSONDecodeError:
                        print(f"[Python] Failed to parse WebSocket message: {msg.data}")
                    except Exception as e:
                        print(f"[Python] Error handling WebSocket message: {e}")
                elif msg.type == aiohttp.WSMsgType.ERROR:
                    print(f"[Python] WebSocket error: {self.websocket.exception()}")
                    break
                elif msg.type == aiohttp.WSMsgType.CLOSE:
                    print("[Python] WebSocket closed by client")
                    break
        except Exception as e:
            print(f"[Python] Message loop error: {e}")
            raise

class LLMAgent:
    def __init__(self, rpc: JsonRpcClient, user_message: str, user_name: str, available_functions: dict):
        self.rpc = rpc
        self.user_message = user_message
        self.user_name = user_name
        self.available_functions = available_functions
        self.gen = self.agent_steps()
        self.current_step = None
        self.response_parts = []
        
    def agent_steps(self):
        print(f"[Agent] LLM Agent starting analysis for {self.user_name}: '{self.user_message}'")
        print(f"[Agent] Available functions: {list(self.available_functions.keys())}")

        self.response_parts = [f"Hello {self.user_name}!"]
        self.response_parts.append(f"I'm analyzing your message \"{self.user_message}\"...")
        
        length = yield ("getStringLength", [self.user_message])
        print(f"[Agent] Length: {length}")
        self.response_parts.append(f" It has {length} characters.")
        
        word_count = yield ("countWords", [self.user_message])
        print(f"[Agent] Word count: {word_count}")
        self.response_parts.append(f" It contains {word_count} word(s) and {length} characters total.")
        
        reversed_text = yield ("reverseString", [self.user_message])
        print(f"[Agent] Reversed: '{reversed_text}'")
        self.response_parts.append(f" When reversed, it becomes: \"{reversed_text}\".")
        
        if length <= 3:
            self.response_parts.append(" That's quite a short message!")
        elif length > 20:
            self.response_parts.append(" That's a nice long message!")

        if self.user_message.lower() == reversed_text.lower():
            self.response_parts.append(" Interesting! Your message is a palindrome, it reads the same forwards and backwards!")

        final_response = " ".join(self.response_parts)
        print("[Agent] All analysis steps completed!")

        return final_response
    
    async def next_step(self):
        try:
            if self.current_step is None:
                self.current_step = next(self.gen)
            
            if isinstance(self.current_step, str):
                print("Agent has completed all analysis steps.")
                return False, self.current_step
            
            method, params = self.current_step
            print(f"[Agent] Executing function call: {method}({params})")
            
            result = await self.rpc.call_method(method, params)
            
            self.current_step = self.gen.send(result)
            
            return True, None
            
        except StopIteration as e:
            # Generator completed, return the final result
            final_result = e.value if hasattr(e, 'value') else " ".join(self.response_parts)
            return False, final_result

async def agent_logic(rpc: JsonRpcClient, user_message: str, user_name: str, available_functions: dict):
    agent = LLMAgent(rpc, user_message, user_name, available_functions)

    while True:
        has_next, final_response = await agent.next_step()
        if not has_next:
            response = final_response
            break
    # Send the agent's response back to PHP
    agent_response_message = {
        "type": "agent_response",
        "data": {
            "response": response,
            "user_message": user_message,
            "user_name": user_name
        }
    }
    print(f"[Python] Sending agent response to PHP: {response}")
    await rpc.websocket.send_json(agent_response_message)

    print("[Python] Closing connection.")
    await rpc.websocket.close()

async def ws_handler(request):
    ws = web.WebSocketResponse(autoping=True)
    await ws.prepare(request)
    print("[Python] PHP client connected via WebSocket")

    # Wait for the initial setup message
    async for msg in ws:
        if msg.type == aiohttp.WSMsgType.TEXT:
            data = json.loads(msg.data)
            if data.get('type') == 'setup':
                setup_data = data.get('data', {})
                user_message = setup_data.get('user_message')
                user_name = setup_data.get('user_name')
                available_functions = setup_data.get('available_functions', {})
                print(f"[Python] Received function metadata: {json.dumps(available_functions, indent=2)}")
                break
        elif msg.type == aiohttp.WSMsgType.ERROR:
            print(f"[Python] WebSocket error during setup: {ws.exception()}")
            return ws
        elif msg.type == aiohttp.WSMsgType.CLOSE:
            print("[Python] WebSocket closed during setup")
            return ws

    rpc = JsonRpcClient(ws)
    msg_task = asyncio.create_task(rpc.message_loop())
    agent_task = asyncio.create_task(agent_logic(rpc, user_message, user_name, available_functions))

    done, pending = await asyncio.wait(
        [msg_task, agent_task], return_when=asyncio.FIRST_COMPLETED
    )
    for task in pending:
        task.cancel()

    return ws


app = web.Application()
app.add_routes([web.get("/ws", ws_handler)])

print("[Python] WebSocket server starting on ws://127.0.0.1:9000/ws")
web.run_app(app, port=9000)