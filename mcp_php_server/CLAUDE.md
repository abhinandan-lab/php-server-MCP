# php_api_mcp Server

## Overview

This MCP server allows natural language interaction to generate and maintain PHP API routes, controller functions, helper functions, database tables structure, and controller-level logic summaries stored in `/api/api_logics/`.

## Guidelines

- Always provide clear instructions describing route methods, parameters, and logic.
- The MCP server responds with a summary of code changes before applying.
- Only internal PHP code files are modified; core framework files remain untouched.
- Summaries per controller are stored in `/api/api_logics/{ControllerName}_summary.txt`.
- Use existing project folder structure for placing controller and helper files.

## Development Notes

- MCP server implemented in Python 3.11 using FastMCP.
- Runs inside Docker container.
- Communicates over stdio transport for integration with Claude Desktop or other MCP clients.
