#!/usr/bin/env python3
"""preToolUse (Write): deny when contents exceed 200 lines."""

import json
import sys
from datetime import datetime, timezone
from pathlib import Path

MAX_LINES = 200
LOG_PATH = Path(__file__).with_name("check-write-length.log")
WARN_ZH = (
    "⚠️ 提醒：即将写入的内容超过 {lines} 行（上限 {max_lines} 行）。"
    "请分批写入：先用 Write 写入前 {max_lines} 行，再通过 StrReplace/追加方式"
    "每次继续写入不超过 {max_lines} 行，直到写完为止。"
)


def log_event(tool_name: str, line_count: int | None, decision: str, note: str = "") -> None:
    ts = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    line_part = "?" if line_count is None else str(line_count)
    entry = f"{ts}\ttool={tool_name}\tlines={line_part}\tdecision={decision}"
    if note:
        entry += f"\tnote={note}"
    try:
        with LOG_PATH.open("a", encoding="utf-8") as fh:
            fh.write(entry + "\n")
    except OSError:
        pass


CONTENT_KEYS = ("content", "contents", "text", "body", "file_text")
EDIT_TOOLS = {"StrReplace", "Edit", "edit_file"}


def extract_write_text(tool_name: str, tool_input: dict) -> str | None:
    for key in CONTENT_KEYS:
        value = tool_input.get(key)
        if value is not None:
            return value if isinstance(value, str) else str(value)

    if tool_name in EDIT_TOOLS:
        new_string = tool_input.get("new_string")
        if new_string is not None:
            return new_string if isinstance(new_string, str) else str(new_string)

    return None


def main() -> int:
    raw = sys.stdin.read()
    if not raw.strip():
        log_event("?", None, "allow", "empty_input")
        print(json.dumps({"permission": "allow"}))
        return 0

    try:
        data = json.loads(raw)
    except json.JSONDecodeError:
        log_event("?", None, "allow", "invalid_json")
        print(json.dumps({"permission": "allow"}))
        return 0

    tool_name = str(data.get("tool_name") or "?")
    tool_input = data.get("tool_input") or {}
    if isinstance(tool_input, str):
        try:
            tool_input = json.loads(tool_input)
        except json.JSONDecodeError:
            tool_input = {}

    contents = extract_write_text(tool_name, tool_input)
    if contents is None:
        keys = ",".join(sorted(tool_input.keys())) if tool_input else "(empty)"
        log_event(tool_name, None, "allow", f"no_contents;keys={keys}")
        print(json.dumps({"permission": "allow"}))
        return 0

    if not isinstance(contents, str):
        contents = str(contents)

    line_count = len(contents.splitlines()) if contents else 0

    if line_count > MAX_LINES:
        agent_message = WARN_ZH.format(lines=line_count, max_lines=MAX_LINES)
        user_message = (
            f"写入内容 {line_count} 行，超过 {MAX_LINES} 行上限，已拦截。"
            "请让 Agent 分批写入。"
        )
        log_event(tool_name, line_count, "deny")
        print(
            json.dumps(
                {
                    "permission": "deny",
                    "user_message": user_message,
                    "agent_message": agent_message,
                },
                ensure_ascii=False,
            )
        )
    else:
        log_event(tool_name, line_count, "allow")
        print(json.dumps({"permission": "allow"}))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
