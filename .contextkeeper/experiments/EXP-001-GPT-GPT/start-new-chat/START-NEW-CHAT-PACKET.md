# START-NEW-CHAT-PACKET.md

## Current Experiment
- Experiment ID: EXP-001-GPT-GPT
- Repo commit: d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7

## Current Authority
- Whitepaper: C:\Users\Steven\contextkeeper-site\docs\whitepapers\EXP-001-GPT-GPT-Handoff-Reliability-v2.0a.docx
- Ledger: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\RUN-LEDGER.csv
- Artifact versions: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\ARTIFACT-VERSIONS.csv

## Latest Active Run
- Run ID: RUN-004
- Run folder: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-004
- Command root: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment
- Upload packet root: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\CURRENT-PACKET
- Current bootstrap prompt: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\CURRENT-BOOTSTRAP-PROMPT.txt
- Response target file: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-004\fresh-chat-response.txt

## Latest Run Metadata
{
    "run_id":  "RUN-004",
    "timestamp_local":  "2026-03-19 20:24:50",
    "transport_condition":  "ATTACHMENT_DIRECT_MINIMAL_BUNDLED",
    "repo_commit_sha":  "d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7",
    "prompt_file":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\runs\\RUN-004\\bootstrap-prompt.txt",
    "current_bootstrap_prompt":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\start-new-chat-experiment\\CURRENT-BOOTSTRAP-PROMPT.txt",
    "response_file":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\runs\\RUN-004\\fresh-chat-response.txt",
    "upload_list_file":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\runs\\RUN-004\\upload-list.txt",
    "upload_packet_root":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\start-new-chat-experiment\\CURRENT-PACKET",
    "command_root":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\start-new-chat-experiment",
    "result":  "PENDING",
    "status":  "STARTED"
}


## Ledger Tail
"run_id","experiment_id","timestamp_local","phase","transport_condition","source_chat_family","target_chat_family","target_model","fresh_chat","artifact_set_version","prompt_version","repo_ref","result","status","rule_failures","notes"
"RUN-001","EXP-001-GPT-GPT","2026-03-19 17:23:46","INITIALIZATION","ATTACHMENT_DIRECT","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-002","EXP-001-GPT-GPT","2026-03-19 18:33:02","INITIALIZATION","ATTACHMENT_DIRECT","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-003","EXP-001-GPT-GPT","2026-03-19 18:58:02","INITIALIZATION","ATTACHMENT_DIRECT_MINIMAL_BUNDLED","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-004","EXP-001-GPT-GPT","2026-03-19 20:24:50","INITIALIZATION","ATTACHMENT_DIRECT_MINIMAL_BUNDLED","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""

## Artifact Version Preview
artifact_set_version,timestamp_local,description,repo_ref,whitepaper_version,control_plane_version,status
EXP001-SET-001,"","Initial GPT-to-GPT experiment baseline","","EXP-001 v2.0a","CP-v1.2","ACTIVE"

## OPERATOR-READY NEXT ACTION
Use these exact paths and files.

### Open this target-chat command folder
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment

### Upload these files from this upload packet folder
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\CURRENT-PACKET

- [UPLOAD FILE LIST UNAVAILABLE]

### Open this local file and copy its full contents exactly
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\CURRENT-BOOTSTRAP-PROMPT.txt

### After the target chat replies, copy the full raw response and run exactly one of:
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Finish-Chat.ps1 -RunId RUN-004 -Result PASS
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Finish-Chat.ps1 -RunId RUN-004 -Result FAIL

## Exact Next Target-Chat Instruction
If the latest run is still STARTED and PENDING:
1. Open a fresh GPT target chat.
2. Go to:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment
3. Upload all bundled files from:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\CURRENT-PACKET
4. Open this file locally:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat-experiment\CURRENT-BOOTSTRAP-PROMPT.txt
5. Copy the CONTENTS of that file.
6. Paste those CONTENTS into the fresh target chat exactly.
7. Send it.
8. Copy the full raw target-chat response.
9. Return to the controller chat.
10. Run:
    C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Finish-Chat.ps1 -RunId RUN-004 -Result PASS
    or
    C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Finish-Chat.ps1 -RunId RUN-004 -Result FAIL

## Controller Re-Handoff Instruction
1. Run:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Start-New-Chat.ps1
2. Open a fresh GPT controller chat.
3. Go to:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\start-new-chat
4. Upload:
   START-NEW-CHAT-PACKET.md
   START-NEW-CHAT-CONTINUITY.md
   START-NEW-CHAT-INSTRUCTIONS.md
5. Paste:

START NEW CHAT

## Trigger Summary
- To replace the controller, use:
  C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Start-New-Chat.ps1
- To start the next target run, use:
  C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Start-New-Chat-Experiment.ps1
