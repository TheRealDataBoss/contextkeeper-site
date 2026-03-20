# FINISH-CHAT-INSTRUCTIONS.md

Finish-Chat.ps1 now auto-classifies the run from the clipboard contents.

Operator procedure:
1. Copy the full raw response from the target chat.
2. Run:
   finish-chat -RunId RUN-XXX

The script will:
- save the raw response
- auto-classify PASS or FAIL
- assign failure classes when appropriate
- update run-metadata.json
- update RUN-LEDGER.csv

No manual pass/fail taxonomy entry is required during normal use.
