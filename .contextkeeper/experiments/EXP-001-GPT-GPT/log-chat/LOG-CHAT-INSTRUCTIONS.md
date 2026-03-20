# LOG-CHAT-INSTRUCTIONS.md

## Purpose
This command records structured experiment observations as local system-of-record artifacts and synchronizes them to GitHub.

## Trigger Name
log-chat

## Current Default Logging Payload
- log_type: CONTROLLER_COMPARISON
- controller_connector_state: MIXED
- target_connector_state: N/A
- best_response: R4
- ranking: R4>R1>R2>R3
- notes: Controller connector ON outperformed OFF; main remaining defect is pending-vs-completed run ambiguity.

## Operator Rule
Run in PowerShell:

log-chat

Do not claim logging is complete until terminal output confirms commit/push or no-op sync.
