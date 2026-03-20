# LOG-CHAT-PROMPT.md

## Trigger Phrase
LOG CHAT

## Purpose
This trigger is for chat use, not terminal execution.

When the operator types:

LOG CHAT

the chat must not claim that logging already happened.
Instead, it must instruct the operator to open PowerShell and run:

log-chat

## Required Chat Response
Return a short instruction that says:

1. Open PowerShell.
2. Run exactly:

   log-chat

3. Explain that this command auto-records the current controller comparison result to local experiment files and GitHub.

## Required Safety Rule
Do not claim the result has been logged unless the operator has actually run the PowerShell command and returned the terminal output.

