# PHP-Python Prototype

A prototype demonstrating a Symfony application using a PHP library to communicate with LLM agents via WebSocket and JSON-RPC.

## Architecture

```
┌─────────────────┐    Library     ┌──────────────────┐    WebSocket     ┌─────────────────┐
│  Symfony App    │ ←──────────→   │   PHP Library    │ ←─────────────→  │      Python     │
│   (Web Demo)    │   Integration  │ (Communication)  │   JSON-RPC       │   (LLM Logic)   │
└─────────────────┘                └──────────────────┘                  └─────────────────┘
```

- **Symfony App**: Web application with FrankenPHP and chatbot interface
- **PHP-Python Library**: Library for communicating with LLM services
- **Python LLM Agent**: Simulates an LLM agent that makes function calls
- **PostgreSQL**: Database for persistence

## Prerequisites

### Option 1: Docker (Recommended)
- **Docker** and **Docker Compose v2**

### Option 2: Local Development
- **PHP 8.4+** with Composer
- **Python 3.11+** with aiohttp
- **PostgreSQL 18** (optional)

## Project Structure

```
php_python_proto/
├── php-python-lib/         # PHP library for LLM agent communication
├── symfony-app/            # Symfony 7.3 application with FrankenPHP
│   └── frankenphp/         # FrankenPHP configuration (Caddyfile, entrypoint)
├── agent-python/           # Python LLM agent server
└── compose.yaml            # Docker Compose configuration
```

## Setup Instructions

### Option 1: Docker Setup (Recommended)

1. **Copy environment file**
```bash
cp .env.example .env
```

2. **Start all services**
```bash
docker compose up -d
```

3. **Access the application**
- Symfony app: http://localhost (HTTP) or https://localhost (HTTPS)
- Python agent: ws://localhost:9000/ws
- PostgreSQL: localhost:5432
- Caddy metrics: http://localhost:2019/metrics

4. **View logs**
```bash
docker compose logs -f symfony-app
```

5. **Stop services**
```bash
docker compose down
```

### Option 2: Local Development Setup

#### 1. Install PHP dependencies

```bash
cd php-python-lib
composer install

cd symfony-app
composer install
```

#### 2. Install Python dependencies

```bash
pip install aiohttp
```

## Running the prototype

### With Docker (Recommended)

```bash
# Start in detached mode
docker compose up -d

# Or with logs
docker compose up
```

### Without Docker

#### Terminal 1: Start Python Server

```bash
cd agent-python
python agent_server.py
```

#### Terminal 2: Start Symfony Application

```bash
cd symfony-app
php -S localhost:8000 -t public
```

### Testing the Application

1. Open your browser and go to: **http://localhost** (Docker) or **http://localhost:8000** (local)
2. Enter your name
3. Send messages to the LLM agent (try words or phrases like "hello", "test message", etc.)
4. Watch the agent analyze your text using the available functions:
   - `getStringLength`: Gets the length of text
   - `countWords`: Counts words in text
   - `reverseString`: Reverses the text

## Docker Stack

- **FrankenPHP**: Modern PHP application server with HTTP/2, HTTP/3, and automatic HTTPS
- **PostgreSQL 18**: Database server
- **Python 3.11**: LLM agent server with aiohttp
- **Caddy**: Built-in web server (via FrankenPHP)

### Features

- Hot-reload for development
- Multi-stage Docker builds (dev/prod)
- Health checks for all services
- Persistent database volume
- Automatic HTTPS certificates

## How It Works

### 1. PHP Library (`php-python-lib`)

The library provides an `AgentClient` class that:
- Connects to the Python WebSocket server
- Registers PHP functions that the agent can call
- Handles JSON-RPC communication
- Returns raw message exchange data for debugging

### 2. Symfony Application

- Provides a simple chatbot web interface
- Runs on FrankenPHP with worker mode for optimal performance
- Uses the PHP library to communicate with the agent
- Displays both the agent response and raw JSON-RPC messages
- Implements demo functions for text analysis

### 3. Python Agent

- Simulates an LLM that makes function calls
- Uses WebSocket for real-time communication
- Implements JSON-RPC 2.0 protocol
