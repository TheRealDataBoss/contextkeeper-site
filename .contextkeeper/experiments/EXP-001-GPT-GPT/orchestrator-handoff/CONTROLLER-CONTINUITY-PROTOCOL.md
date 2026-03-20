# CONTROLLER-CONTINUITY-PROTOCOL.md

## Purpose
This protocol governs controller/orchestrator chat continuity for EXP-001-GPT-GPT.
It exists so a fresh GPT controller chat can take over at any time without relying
on prior chat memory.

## Governing Rule
The controller chat is replaceable.
The local experiment files are the system of record.
A controller chat must never treat itself as the source of truth.

## System of Record
- RUN-LEDGER.csv
- ARTIFACT-VERSIONS.csv
- HANDOFF.md
- current experiment scripts
- current controller handoff packet
- canonical whitepaper
- latest run folder

## Required Controller Duties
- preserve experiment design unless a logged failure justifies a versioned change
- separate controller-handoff testing from target-handoff testing
- instruct the operator using exact local file paths
- use local files, not prior chat memory, as authority
- log controller continuity events and target run outcomes

## Trigger For Controller Handoff
A controller chat should prepare handoff when any of the following is true:
- the chat is becoming long or hard to navigate
- context recovery is becoming unreliable
- token pressure is suspected
- the operator wants a fresh controller chat
- the current controller has completed a major setup phase

## Required Handoff Artifacts
A fresh controller chat must receive at minimum:
- CONTROLLER-HANDOFF-PACKET.md
- CONTROLLER-CONTINUITY-PROTOCOL.md

Optional supporting files if needed:
- RUN-LEDGER.csv
- ARTIFACT-VERSIONS.csv
- HANDOFF.md
- latest run metadata
- canonical whitepaper

## Required Fresh Controller Response
A fresh controller chat must return exactly:
1. Confirmed controller state
2. Current experiment status
3. Latest completed action
4. Exact next target-chat instruction
5. Exact post-response logging instruction
6. Controller re-handoff instruction
7. Constraints and risks

## Controller Re-Handoff Instruction Requirement
Every controller chat must always be able to tell the operator how to create
the next controller handoff. This instruction must reference the local script:

C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Start-Orchestrator-Handoff.ps1

## Operator Rule
If controller quality degrades, do not rescue the controller with conversation.
Generate a new controller handoff packet and move to a fresh GPT chat.
