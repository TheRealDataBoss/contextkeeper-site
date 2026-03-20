# START-NEW-CHAT-PACKET.md

## Current Experiment
- Experiment ID: EXP-001-GPT-GPT
- Repo commit: c0b8f086c11e453446abab5ec540f5aeb2c436a7

## Current Authority
- Whitepaper: C:\Users\Steven\contextkeeper-site\docs\whitepapers\EXP-001-GPT-GPT-Handoff-Reliability-v2.0a.docx
- Ledger: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\RUN-LEDGER.csv
- Artifact versions: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\ARTIFACT-VERSIONS.csv

## Current Active Run
- Run ID: RUN-006
- Run folder: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-006
- Transport condition: GITHUB_CONNECTOR_ONLY
- Status: COMPLETED
- Result: FAIL
- Timestamp local: 2026-03-19 22:12:57
- Command root: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\github-connector
- Upload packet root: 
- Current prompt path: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\github-connector\CURRENT-GITHUB-CONNECTOR-PROMPT.txt
- Response target file: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-006\fresh-chat-response.txt

## Ledger Tail
"RUN-002","EXP-001-GPT-GPT","2026-03-19 18:33:02","INITIALIZATION","ATTACHMENT_DIRECT","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-003","EXP-001-GPT-GPT","2026-03-19 18:58:02","INITIALIZATION","ATTACHMENT_DIRECT_MINIMAL_BUNDLED","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-004","EXP-001-GPT-GPT","2026-03-19 20:24:50","INITIALIZATION","ATTACHMENT_DIRECT_MINIMAL_BUNDLED","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-005","EXP-001-GPT-GPT","2026-03-19 21:08:40","INITIALIZATION","GITHUB_CONNECTOR_ONLY","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","1030aa873c1d79dbccbeb088fcc969cbe676a235","FAIL","COMPLETED","Target response was empty or too short to evaluate","PRIMARY=EMPTY_OR_TRUNCATED_RESPONSE | SECONDARY=LOW_INFORMATION_OUTPUT | CONFIDENCE=HIGH | Response text too short for controlled bootstrap validation."
"RUN-006","EXP-001-GPT-GPT","2026-03-19 22:12:57","INITIALIZATION","GITHUB_CONNECTOR_ONLY","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","141ab2ead9bf3c8b82c3cfa6c43caeb5ea3ae5fa","FAIL","COMPLETED","Target response was empty or too short to evaluate","PRIMARY=EMPTY_OR_TRUNCATED_RESPONSE | SECONDARY=LOW_INFORMATION_OUTPUT | CONFIDENCE=HIGH | Response text too short for controlled bootstrap validation."

## Artifact Version Preview
artifact_set_version,timestamp_local,description,repo_ref,whitepaper_version,control_plane_version,status
EXP001-SET-001,"","Initial GPT-to-GPT experiment baseline","","EXP-001 v2.0a","CP-v1.2","ACTIVE"

## Operator-Ready Next Action
Open a fresh GPT target chat.

Enable the GitHub connector for:
TheRealDataBoss/contextkeeper-site

Do not upload prompt-box files for this run.

Open locally:
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\github-connector\CURRENT-GITHUB-CONNECTOR-PROMPT.txt

Copy the full contents exactly.

Paste into the fresh target chat.

Send the message.

Copy the full raw response.

## Exact Post-Response Logging Instruction
Copy the full raw target-chat response to your clipboard.

Open PowerShell.

Run exactly:

finish-chat

## Controller Re-Handoff Instruction
Open PowerShell.

Run exactly:

start-new-chat

Open a fresh GPT controller chat.

Go to:
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat

Upload:
START-NEW-CHAT-PACKET.md
START-NEW-CHAT-CONTINUITY.md
START-NEW-CHAT-INSTRUCTIONS.md

Paste exactly:

START NEW CHAT
