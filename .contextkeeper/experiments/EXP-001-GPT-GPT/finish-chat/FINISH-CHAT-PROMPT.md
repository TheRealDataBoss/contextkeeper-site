# FINISH-CHAT-PROMPT.md

## Trigger Phrase
FINISH CHAT

## Purpose
This trigger is for chat use, not terminal execution.

When the operator types:

FINISH CHAT

the chat must not pretend to execute the local script.
Instead, it must instruct the operator exactly what to do in PowerShell.

## Required Chat Response
Return a short instruction that says:

1. Copy the full raw target-chat response to the clipboard.
2. Open PowerShell.
3. Run exactly:

   finish-chat

4. Explain that this command auto-detects the latest pending run, saves the clipboard contents, auto-classifies PASS/FAIL, and updates the ledger.

## Required Safety Rule
Do not claim the run is finished unless the operator has actually run the PowerShell command and returned the terminal output.
