import asyncio, json, uuid
from aiohttp import web

class JsonRpcClient:
    def __init__(self, websocket: web.WebSocketResponse):
        self.websocket = websocket
        self.pending = {}  # id -> Future

    async def call_method(self, method: str, params=None, timeout: float = 5.0):
        if self.websocket.closed:
            raise RuntimeError("WebSocket closed")

        rid = str(uuid.uuid4())
        rpc = {"jsonrpc": "2.0", "method": method, "id": rid}

        if params is not None:
            rpc["params"] = params
            
        wrapper = {"type": "rpc_call", "id": rid, "data": rpc}

        loop = asyncio.get_event_loop()
        fut = loop.create_future()
        self.pending[rid] = fut

        await self.websocket.send_json(wrapper)
        print(f"[Python] Sent RPC: {json.dumps(rpc)}")

        try:
            result = await asyncio.wait_for(fut, timeout=timeout)
            return result
        finally:
            self.pending.pop(rid, None)

    async def handle_message(self, msg: dict):
        if msg.get("type") == "rpc_response":
            rid = msg.get("id")
            data = msg.get("data", {})
            fut = self.pending.get(rid)
            if fut and not fut.done():
                if "error" in data:
                    e = data["error"]
                    fut.set_exception(Exception(f"RPC Error {e.get('code')}: {e.get('message')} ({e.get('data')})"))
                else:
                    fut.set_result(data.get("result"))
        else:
            print(f"[Python] Non-RPC message: {msg}")

    async def message_loop(self):
        async for wsmsg in self.websocket:
            if wsmsg.type == web.WSMsgType.TEXT:
                try:
                    await self.handle_message(json.loads(wsmsg.data))
                except Exception as e:
                    print(f"[Python] Bad message: {e}")
            elif wsmsg.type == web.WSMsgType.ERROR:
                logger.error(f"WebSocket error: {self.websocket.exception()}")
                break
            elif wsmsg.type == web.WSMsgType.CLOSE:
                logger.info("WebSocket closed by client")
                break

class LLMAgent:
    def __init__(self, rpc: JsonRpcClient):
        self.rpc = rpc
        self.gen = self.agent_steps()
        self.current_step = None
        self.step_count = 0
        
    def agent_steps(self):
        """Générateur qui simule les étapes d'un agent LLM"""
        print("[Agent] LLM Agent starting...")
        
        print("[Agent] Step 1: List available functions...")
        funcs = yield ("listFunctions", None)
        print(f"[Agent] Available functions: {funcs}")
        
        print("[Agent] Step 2: Analyzing functions and planning...")
        print("[Agent] I can see 'sayHello' and 'add' functions. Let me test them!")
        
        names_to_test = ["Alice", "Bob"]
        for name in names_to_test:
            print(f"[Agent] Step {self.step_count + 3}: Testing sayHello with '{name}'...")
            result = yield ("sayHello", [name])
            print(f"[Agent] sayHello('{name}') -> {result}")
            self.step_count += 1
        
        operations = [(5, 7), (10, 15)]
        for a, b in operations:
            print(f"[Agent] Step {self.step_count + 3}: Computing {a} + {b}...")
            result = yield ("add", [a, b])
            print(f"[Agent] add({a}, {b}) -> {result}")
            self.step_count += 1
        
        print("[Agent] All steps completed!")
        return "DONE"
    
    async def next_step(self):
        try:
            if self.current_step is None:
                self.current_step = next(self.gen)
            
            if self.current_step == "DONE":
                print("Agent has completed all steps.")
                return False
            
            method, params = self.current_step
            print(f"Executing function call: {method}({params})")
            
            result = await self.rpc.call_method(method, params)
            
            self.current_step = self.gen.send(result)
            
            return True
            
        except StopIteration as e:
            return False
        except Exception as e:
            print(f"Error in agent step: {e}")
            return False

async def agent_logic(rpc: JsonRpcClient):
    agent = LLMAgent(rpc)

    while True:
        has_next = await agent.next_step()
        if not has_next:
            break

    print("\nLLM Agent session completed. Closing connection.")
    await rpc.websocket.close()

async def ws_handler(request):
    ws = web.WebSocketResponse(autoping=True)
    await ws.prepare(request)
    print("[Python] PHP bridge connected.")

    rpc = JsonRpcClient(ws)
    msg_task = asyncio.create_task(rpc.message_loop())
    agent_task = asyncio.create_task(agent_logic(rpc))

    done, pending = await asyncio.wait(
        [msg_task, agent_task], return_when=asyncio.FIRST_COMPLETED
    )
    for t in pending:
        t.cancel()
    return ws


app = web.Application()
app.add_routes([web.get("/ws", ws_handler)])

print("Python: WS server on ws://127.0.0.1:9000/ws")
web.run_app(app, port=9000)
