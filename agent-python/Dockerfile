FROM python:3.11-slim

WORKDIR /app

RUN pip install --no-cache-dir aiohttp

COPY agent_server.py .

EXPOSE 9000

CMD ["python", "agent_server.py"]
