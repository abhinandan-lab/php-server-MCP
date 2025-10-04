# php_api_mcp Server

A Model Context Protocol (MCP) server that generates and modifies PHP API routes, controllers, helper functions, and maintains API logic summaries based on natural language instructions.

## Purpose

This MCP server provides a natural language interface to create, update, and document PHP APIs and their associated logic in a PHP framework project.

## Features

### Current Implementation

- **create_or_update_api** - Generate or update PHP API route, controller method, helper functions, and update tables and logic summary.
- **list_supported_operations** - Lists available MCP server operations.

## Prerequisites

- Docker Desktop with MCP Toolkit enabled.
- Docker MCP CLI plugin (`docker mcp` command).
- Python 3.11 environment (handled via Dockerfile).

## Installation

Follow the step-by-step instructions in the installation section.

## Usage Examples

Ask the MCP server in Claude Desktop or other MCP clients:

- "Create a GET API route `/example` in ExampleController that returns a hello message."
- "Update the helper function in exampleHelper.php to add new functionality."
- "Modify the tables.txt file to add a new table for tracking examples."

## Architecture

