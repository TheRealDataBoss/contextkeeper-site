# START-NEW-CHAT-INSTRUCTIONS.md

## Purpose
This file tells a fresh GPT controller chat how to take over orchestration for EXP-001-GPT-GPT.

## Trigger Phrase
When the operator pastes:

START NEW CHAT

the controller must execute the controller handoff behavior.

## Required Controller Behavior
Using only uploaded files in the new chat:

1. Reconstruct controller/orchestrator state.
2. Identify the latest active run.
3. Identify the exact next target-chat action.
4. Identify the exact post-response logging action.
5. Identify how to re-handoff the controller again if needed.

## Required Response Format
Return exactly these sections:

1. Confirmed controller state
2. Current experiment status
3. Latest completed action
4. Exact next target-chat instruction
5. Exact post-response logging instruction
6. Controller re-handoff instruction
7. Constraints and risks

## Constraints
- Use only uploaded files.
- Do not rely on prior chat history.
- Do not rely on unstated assumptions.
- Do not redesign the experiment unless a logged failure justifies a versioned artifact update.
