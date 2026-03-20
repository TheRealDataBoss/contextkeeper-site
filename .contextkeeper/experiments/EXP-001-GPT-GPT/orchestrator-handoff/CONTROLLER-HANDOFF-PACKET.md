# CONTROLLER-HANDOFF-PACKET.md

## Purpose
This file is the controller/orchestrator handoff packet for a fresh GPT controller chat.
It must allow the fresh controller chat to continue supervision immediately.

## Current Experiment
- Experiment ID: EXP-001-GPT-GPT
- Scope: GPT-to-GPT handoff reliability only
- Repo: TheRealDataBoss/contextkeeper-site
- Repo commit: d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7

## Current Authority
- Whitepaper: C:\Users\Steven\contextkeeper-site\docs\whitepapers\EXP-001-GPT-GPT-Handoff-Reliability-v2.0a.docx
- Ledger: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\RUN-LEDGER.csv
- Artifact versions: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\ARTIFACT-VERSIONS.csv
- Continuity protocol: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\orchestrator-handoff\CONTROLLER-CONTINUITY-PROTOCOL.md
- start-chat instructions: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\orchestrator-handoff\start-chat-INSTRUCTIONS.md

## Latest Active Run
- Run ID: RUN-003
- Run folder: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-003
- Run metadata: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-003\run-metadata.json
- Bootstrap prompt file: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-003\bootstrap-prompt.txt
- Upload list file: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-003\upload-list.txt
- Response target file: C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-003\fresh-chat-response.txt

## Latest Run Metadata
{
    "run_id":  "RUN-003",
    "timestamp_local":  "2026-03-19 18:58:02",
    "transport_condition":  "ATTACHMENT_DIRECT_MINIMAL_BUNDLED",
    "repo_commit_sha":  "d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7",
    "prompt_file":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\runs\\RUN-003\\bootstrap-prompt.txt",
    "response_file":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\runs\\RUN-003\\fresh-chat-response.txt",
    "upload_list_file":  "C:\\Users\\Steven\\contextkeeper-site\\.contextkeeper\\experiments\\EXP-001-GPT-GPT\\runs\\RUN-003\\upload-list.txt",
    "result":  "PENDING",
    "status":  "STARTED"
}


## Latest Run Bootstrap Prompt Content
You are starting as a fresh GPT chat for a controlled GPT-to-GPT handoff run.

Use only the materials provided through the declared handoff channel.
Do not rely on prior chat history, external memory, or unstated assumptions.

Read the provided handoff artifacts in order, then return exactly these sections:

1. Confirmed state
2. Active task
3. Constraints
4. Unknowns
5. First action you will take

If any required artifact is missing, say so explicitly.



## Latest Run Upload List
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\attachment-bundles\00-BOOTSTRAP-PROMPT.txt
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\attachment-bundles\01-CONTROL-PLANE.md
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\attachment-bundles\02-DOCS-AND-SCHEMA.md
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\attachment-bundles\03-SOURCE-CODE.md
C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\attachment-bundles\04-EXPERIMENT-CONTEXT.md


## Ledger Tail
"run_id","experiment_id","timestamp_local","phase","transport_condition","source_chat_family","target_chat_family","target_model","fresh_chat","artifact_set_version","prompt_version","repo_ref","result","status","rule_failures","notes"
"RUN-001","EXP-001-GPT-GPT","2026-03-19 17:23:46","INITIALIZATION","ATTACHMENT_DIRECT","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-002","EXP-001-GPT-GPT","2026-03-19 18:33:02","INITIALIZATION","ATTACHMENT_DIRECT","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""
"RUN-003","EXP-001-GPT-GPT","2026-03-19 18:58:02","INITIALIZATION","ATTACHMENT_DIRECT_MINIMAL_BUNDLED","GPT","GPT","GPT-5.4 Thinking","TRUE","EXP001-SET-001","BOOTSTRAP-v1","d0c5beb46fbc6cb33c6a6ef16ff9a01ec1c4f1c7","PENDING","STARTED","",""

## Artifact Version Preview
artifact_set_version,timestamp_local,description,repo_ref,whitepaper_version,control_plane_version,status
EXP001-SET-001,"","Initial GPT-to-GPT experiment baseline","","EXP-001 v2.0a","CP-v1.2","ACTIVE"

## Exact Next Target-Chat Instruction
If the latest run is still STARTED and PENDING:
1. Open a fresh GPT target chat.
2. Upload every file listed in:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-003\upload-list.txt
3. Open this file locally:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\runs\RUN-003\bootstrap-prompt.txt
4. Copy the CONTENTS of that file.
5. Paste those CONTENTS into the fresh target chat exactly.
6. Send it.
7. Copy the full raw target-chat response.
8. Return to the controller chat.
9. Run:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Finish-Chat.ps1 -RunId RUN-003 -Result PASS
   or
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\Finish-Chat.ps1 -RunId RUN-003 -Result FAIL

## Controller Re-Handoff Instruction
If this controller chat needs replacement at any time:
1. Run:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\start-chat.ps1
2. Open a fresh GPT controller chat.
3. Upload these three files only:
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\orchestrator-handoff\CONTROLLER-HANDOFF-PACKET.md
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\orchestrator-handoff\CONTROLLER-CONTINUITY-PROTOCOL.md
   C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\orchestrator-handoff\start-chat-INSTRUCTIONS.md
4. Paste exactly:

START CHAT

## Trigger Summary
- To replace the controller, use:
  C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\start-chat.ps1
- To start the next target run, use:
  C:\Users\Steven\contextkeeper-site\.contextkeeper\experiments\EXP-001-GPT-GPT\scripts\start-chat-experiment.ps1

