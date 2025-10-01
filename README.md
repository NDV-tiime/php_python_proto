# PHP-Python Prototype

A prototype demonstrating a Symfony application using a PHP library to communicate with LLM agents via WebSocket and JSON-RPC.

## Architecture

```
┌─────────────────┐    Library     ┌──────────────────┐    WebSocket     ┌─────────────────┐
│  Symfony App    │ ←──────────→   │   PHP Library    │ ←─────────────→  │      Python     │
│   (Web Demo)    │   Integration  │ (Communication)  │   JSON-RPC       │   (LLM Logic)   │
└─────────────────┘                └──────────────────┘                  └─────────────────┘
```

- **Symfony App**: Web application with a chatbot interface
- **PHP-Python Library**: Library for communicating with LLM services
- **Python LLM Agent**: Simulates an LLM agent that makes function calls

## Prerequisites

- **PHP 8.2+** with Composer
- **Python 3.7+** with aiohttp

## Project Structure

```
php_python_proto/
├── php-python-lib/         # PHP library for LLM agent communication
├── symfony-app/            # Symfony application with chatbot
└── agent-python/           # Python LLM agent server
```

## Setup Instructions

### 1. Install PHP dependencies

```bash
cd php-python-lib
composer install

cd symfony-app
composer install
```

### 2. Install Python dependencies

```bash
pip install aiohttp
```

## Running the prototype

### Terminal 1: Start Python Server

```bash
cd agent-python
python agent_server.py
```

### Terminal 2: Start Symfony Application

```bash
cd symfony-app
php -S localhost:8000 -t public
```

### Testing the Application

1. Open your browser and go to: **http://localhost:8000**
2. Enter your name
3. Send messages to the LLM agent (try words or phrases like "hello", "test message", etc.)
4. Watch the agent analyze your text using the available functions:
   - `getStringLength`: Gets the length of text
   - `countWords`: Counts words in text
   - `reverseString`: Reverses the text
   - `greetUser`: Greets the user

## How It Works

### 1. PHP Library (`php-python-lib`)

The library provides an `AgentClient` class that:
- Connects to the Python WebSocket server
- Registers PHP functions that the agent can call
- Handles JSON-RPC communication
- Returns raw message exchange data for debugging

### 2. Symfony Application

- Provides a simple chatbot web interface
- Uses the PHP library to communicate with the agent
- Displays both the agent response and raw JSON-RPC messages
- Implements demo functions for text analysis

### 3. Python Agent

- Simulates an LLM that makes function calls