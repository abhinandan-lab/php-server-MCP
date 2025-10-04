#!/usr/bin/env python3
import os
import sys
import logging
from datetime import datetime
from pathlib import Path
from mcp.server.fastmcp import FastMCP

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s', stream=sys.stderr)
logger = logging.getLogger("php_api_mcp_server")

mcp = FastMCP("php_api_mcp")

# Project root from env or fallback
PHP_PROJECT_ROOT = Path(
    os.environ.get("PROJECT_ROOT", r"C:\xampp\htdocs\php_server_MCP2")
).resolve()

API_FOLDER = PHP_PROJECT_ROOT / "api"
HELPERS_FOLDER = API_FOLDER / "helpers"
TABLES_FILE = API_FOLDER / "tables"
API_LOGICS_FOLDER = API_FOLDER / "api_logics"

# Safe path resolver (prevents escaping the root)
def resolve_under_root(p: str | Path) -> Path:
    path = Path(p)
    if not path.is_absolute():
        path = PHP_PROJECT_ROOT / path
    path = path.resolve()
    # Ensure path is inside root
    try:
        path.relative_to(PHP_PROJECT_ROOT)
    except ValueError:
        raise ValueError(f"Path escapes project root: {path}")
    return path

# Ensure folders exist under project root
for _dir in (API_FOLDER, HELPERS_FOLDER, API_LOGICS_FOLDER):
    _dir.mkdir(parents=True, exist_ok=True)

@mcp.tool()
async def read_file(filepath: str = "") -> str:
    if not filepath.strip():
        return "❌ Error: Filepath is required"
    try:
        path = resolve_under_root(filepath)
        if not path.exists():
            return f"❌ Error: File {path} does not exist"
        return f"✅ File content of {path}:\n\n{path.read_text(encoding='utf-8')}"
    except Exception as e:
        logger.exception("read_file error")
        return f"❌ Error reading file: {e}"

@mcp.tool()
async def write_file(filepath: str = "", content: str = "") -> str:
    if not filepath.strip():
        return "❌ Error: Filepath is required"
    if not content.strip():
        return "❌ Error: Content cannot be empty"
    try:
        path = resolve_under_root(filepath)
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8")
        return f"✅ Successfully wrote to {path}"
    except Exception as e:
        logger.exception("write_file error")
        return f"❌ Error writing file: {e}"

@mcp.tool()
async def list_files(directory: str = "") -> str:
    try:
        # Default to api folder under root
        target = resolve_under_root(directory.strip() or str(API_FOLDER))
        if not target.exists():
            return f"❌ Error: Directory {target} does not exist"
        files = [str(p) for p in target.rglob("*") if p.is_file()]
        return f"✅ Files in {target}:\n" + "\n".join(files) if files else f"ℹ️ No files found in {target}"
    except Exception as e:
        logger.exception("list_files error")
        return f"❌ Error: {e}"

@mcp.tool()
async def append_to_file(filepath: str = "", content: str = "") -> str:
    if not filepath.strip():
        return "❌ Error: Filepath is required"
    if not content.strip():
        return "❌ Error: Content cannot be empty"
    try:
        path = resolve_under_root(filepath)
        existing = path.read_text(encoding="utf-8") if path.exists() else ""
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(existing + ("\n" if existing else "") + content, encoding="utf-8")
        return f"✅ Successfully appended to {path}"
    except Exception as e:
        logger.exception("append_to_file error")
        return f"❌ Error: {e}"

@mcp.tool()
async def delete_file(filepath: str = "") -> str:
    if not filepath.strip():
        return "❌ Error: Filepath is required"
    try:
        path = resolve_under_root(filepath)
        if not path.exists():
            return f"❌ Error: File {path} does not exist"
        path.unlink()
        return f"✅ Successfully deleted {path}"
    except Exception as e:
        logger.exception("delete_file error")
        return f"❌ Error: {e}"

@mcp.tool()
async def create_controller(controller_name: str = "", functions: str = "") -> str:
    if not controller_name.strip():
        return "❌ Error: Controller name is required"
    try:
        controllers_folder = API_FOLDER / "controllers"
        controllers_folder.mkdir(parents=True, exist_ok=True)

        controller_code = f"""<?php

class {controller_name} extends BaseController {{

    public function __construct() {{
        parent::__construct();
    }}

    {functions if functions.strip() else "// Add your methods here"}
}}
"""
        controller_file = controllers_folder / f"{controller_name}.php"
        controller_file.write_text(controller_code, encoding="utf-8")

        summary_file = API_LOGICS_FOLDER / f"{controller_name}_summary.txt"
        summary_file.write_text(
            f"""API Logic Summary for controller: {controller_name}
Generated on {datetime.utcnow().isoformat()} UTC

Controller: {controller_name}
Location: api/controllers/{controller_name}.php
Functions: {functions if functions.strip() else "None specified yet"}
""",
            encoding="utf-8",
        )
        return f"✅ Created {controller_file}\n✅ Created {summary_file}"
    except Exception as e:
        logger.exception("create_controller error")
        return f"❌ Error: {e}"

if __name__ == "__main__":
    logger.info(f"Starting PHP API MCP server with root: {PHP_PROJECT_ROOT}")
    logger.info(f"Current working directory: {Path.cwd()}")
    # Optional: make relative paths behave as expected
    try:
        os.chdir(PHP_PROJECT_ROOT)
    except Exception:
        pass
    mcp.run(transport='stdio')
