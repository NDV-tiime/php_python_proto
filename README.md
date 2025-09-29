# PHP-Python RPC Prototype

A prototype demonstrating communication between a Python LLM Agent and PHP consumer functions via WebSocket and JSON-RPC.

## Architecture

```
Python Agent (LLM) ←→ WebSocket ←→ PHP Bridge ←→ Symfony Consumer App
```

- **Python Agent**: simulates an LLM agent capable of calling functions from the consumer app.
- **PHP Bridge**: webSocket client that translates between WebSocket and JSON-RPC
- **Consumer App**: Symfony application implementing functions that the LLM agent can call

## Prerequisites

- **PHP 8.1+** with Composer
- **Python 3.8+**

## Quick Setup

```bash
cd bridge-php
composer install

cd consumer-app
composer install

pip install aiohttp
```

## Running the Prototype

### Terminal 1: Start Python Agent Server

```bash
python agent-python/agent_server.py
```



### Terminal 2: Start PHP Bridge

```bash
php bridge-php/bridge.php
```