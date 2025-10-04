import subprocess
import json

def send_request(process, message):
    process.stdin.write(json.dumps(message) + "\n")
    process.stdin.flush()
    response = process.stdout.readline()
    return response.strip()

process = subprocess.Popen(
    ["python", "mcp_php_api_server.py"],
    stdin=subprocess.PIPE,
    stdout=subprocess.PIPE,
    text=True
)

# Step 1: Initialize
init_request = {
    "jsonrpc": "2.0",
    "method": "initialize",
    "id": 1,
    "params": {"capabilities": {}}
}
print("Sending initialize...")
process.stdin.write(json.dumps(init_request) + "\n")
process.stdin.flush()

print("Response:", process.stdout.readline())

# Step 2: List tools
tools_request = {"jsonrpc": "2.0", "method": "tools/list", "id": 2}
print("Sending tools/list...")
process.stdin.write(json.dumps(tools_request) + "\n")
process.stdin.flush()

print("Response:", process.stdout.readline())

process.stdin.close()
process.stdout.close()
process.terminate()
