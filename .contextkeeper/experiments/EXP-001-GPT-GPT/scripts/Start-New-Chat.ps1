$ErrorActionPreference = "Stop"

$RepoRoot = "C:\Users\Steven\contextkeeper-site"
$ExpRoot = Join-Path $RepoRoot ".contextkeeper\experiments\EXP-001-GPT-GPT"
$OutRoot = Join-Path $ExpRoot "start-new-chat"
$RunsRoot = Join-Path $ExpRoot "runs"
$ScriptsRoot = Join-Path $ExpRoot "scripts"

New-Item -ItemType Directory -Path $OutRoot -Force | Out-Null

Push-Location $RepoRoot
$RepoCommit = (git rev-parse HEAD).Trim()
Pop-Location

$LedgerPath = Join-Path $ExpRoot "RUN-LEDGER.csv"
$ArtifactPath = Join-Path $ExpRoot "ARTIFACT-VERSIONS.csv"
$WhitepaperPath = Join-Path $RepoRoot "docs\whitepapers\EXP-001-GPT-GPT-Handoff-Reliability-v2.0a.docx"
$ContinuityPath = Join-Path $OutRoot "START-NEW-CHAT-CONTINUITY.md"
$InstructionsPath = Join-Path $OutRoot "START-NEW-CHAT-INSTRUCTIONS.md"
$ControllerScriptPath = Join-Path $ScriptsRoot "Start-New-Chat.ps1"
$FinishChatCommand = "finish-chat"

$LatestRun = Get-ChildItem $RunsRoot -Directory | Sort-Object Name | Select-Object -Last 1
$LatestRunName = if ($LatestRun) { $LatestRun.Name } else { "NONE" }
$LatestRunRoot = if ($LatestRun) { $LatestRun.FullName } else { "" }

$LatestRunMetaPath = if ($LatestRun) { Join-Path $LatestRunRoot "run-metadata.json" } else { "" }
$LatestRunResponsePath = if ($LatestRun) { Join-Path $LatestRunRoot "fresh-chat-response.txt" } else { "" }

$MetaObj = $null
if ($LatestRunMetaPath -and (Test-Path $LatestRunMetaPath)) {
    $MetaObj = Get-Content $LatestRunMetaPath -Raw | ConvertFrom-Json
}

$TransportCondition = if ($MetaObj -and $MetaObj.transport_condition) { [string]$MetaObj.transport_condition } else { "" }
$CommandRoot = if ($MetaObj -and $MetaObj.command_root) { [string]$MetaObj.command_root } else { "" }
$UploadPacketRoot = if ($MetaObj -and $MetaObj.upload_packet_root) { [string]$MetaObj.upload_packet_root } else { "" }
$CurrentPromptPath = if ($MetaObj -and $MetaObj.current_prompt_path) { [string]$MetaObj.current_prompt_path } else { "" }
$RunStatus = if ($MetaObj -and $MetaObj.status) { [string]$MetaObj.status } else { "" }
$RunResult = if ($MetaObj -and $MetaObj.result) { [string]$MetaObj.result } else { "" }
$RunTimestamp = if ($MetaObj -and $MetaObj.timestamp_local) { [string]$MetaObj.timestamp_local } else { "" }

$UploadFileNames = @()
if ($UploadPacketRoot -and (Test-Path $UploadPacketRoot)) {
    $UploadFileNames = Get-ChildItem $UploadPacketRoot -File | Sort-Object Name | ForEach-Object { $_.Name }
}

$UploadFileListText = if ($UploadFileNames.Count -gt 0) {
    ($UploadFileNames | ForEach-Object { "- $_" }) -join [Environment]::NewLine
} else {
    ""
}

$LedgerPreview = ""
if (Test-Path $LedgerPath) {
    $LedgerPreview = (Get-Content $LedgerPath | Select-Object -Last 5) -join [Environment]::NewLine
}

$ArtifactPreview = ""
if (Test-Path $ArtifactPath) {
    $ArtifactPreview = (Get-Content $ArtifactPath | Select-Object -First 10) -join [Environment]::NewLine
}

$TargetInstruction = switch ($TransportCondition) {
    "PROMPT_ATTACHMENT" {
@"
Open a fresh GPT target chat.

Go to:
$CommandRoot

Upload all files from:
$UploadPacketRoot

$UploadFileListText

Open locally:
$CurrentPromptPath

Copy the full contents exactly.

Paste into the fresh target chat.

Send the message.

Copy the full raw response.
"@
    }
    "GITHUB_CONNECTOR_ONLY" {
@"
Open a fresh GPT target chat.

Enable the GitHub connector for:
TheRealDataBoss/contextkeeper-site

Do not upload prompt-box files for this run.

Open locally:
$CurrentPromptPath

Copy the full contents exactly.

Paste into the fresh target chat.

Send the message.

Copy the full raw response.
"@
    }
    "HYBRID" {
@"
Open a fresh GPT target chat.

Enable the GitHub connector for:
TheRealDataBoss/contextkeeper-site

Do not upload prompt-box files unless the prompt explicitly requires it.

Open locally:
$CurrentPromptPath

Copy the full contents exactly.

Paste into the fresh target chat.

Send the message.

Copy the full raw response.
"@
    }
    default {
@"
Open a fresh GPT target chat.

Open locally:
$CurrentPromptPath

Copy the full contents exactly.

Paste into the fresh target chat.

Send the message.

Copy the full raw response.
"@
    }
}

$Instructions = @"
# START-NEW-CHAT-INSTRUCTIONS.md

## Trigger Phrase
START NEW CHAT

## Required Response Sections
1. Confirmed controller state
2. Current experiment status
3. Latest completed action
4. Exact next target-chat instruction
5. Exact post-response logging instruction
6. Controller re-handoff instruction
7. Constraints and risks

## Controller Standard
Use exact absolute paths when present.
Do not replace exact paths with ellipses, placeholders, or summaries.
Use the current active run dynamically.
Use the operator command `finish-chat` for post-response logging instructions.
"@
Set-Content -Path $InstructionsPath -Value $Instructions -Encoding UTF8

$Continuity = @"
# START-NEW-CHAT-CONTINUITY.md

Controller handoff files for this command live in:
$OutRoot

A fresh controller chat must receive:
- START-NEW-CHAT-PACKET.md
- START-NEW-CHAT-CONTINUITY.md
- START-NEW-CHAT-INSTRUCTIONS.md

Controller replacement command:
start-new-chat

Target run completion command:
finish-chat
"@
Set-Content -Path $ContinuityPath -Value $Continuity -Encoding UTF8

$Packet = @"
# START-NEW-CHAT-PACKET.md

## Current Experiment
- Experiment ID: EXP-001-GPT-GPT
- Repo commit: $RepoCommit

## Current Authority
- Whitepaper: $WhitepaperPath
- Ledger: $LedgerPath
- Artifact versions: $ArtifactPath

## Current Active Run
- Run ID: $LatestRunName
- Run folder: $LatestRunRoot
- Transport condition: $TransportCondition
- Status: $RunStatus
- Result: $RunResult
- Timestamp local: $RunTimestamp
- Command root: $CommandRoot
- Upload packet root: $UploadPacketRoot
- Current prompt path: $CurrentPromptPath
- Response target file: $LatestRunResponsePath

## Ledger Tail
$LedgerPreview

## Artifact Version Preview
$ArtifactPreview

## Operator-Ready Next Action
$TargetInstruction

## Exact Post-Response Logging Instruction
Copy the full raw target-chat response to your clipboard.

Open PowerShell.

Run exactly:

$FinishChatCommand

## Controller Re-Handoff Instruction
Open PowerShell.

Run exactly:

start-new-chat

Open a fresh GPT controller chat.

Go to:
$OutRoot

Upload:
START-NEW-CHAT-PACKET.md
START-NEW-CHAT-CONTINUITY.md
START-NEW-CHAT-INSTRUCTIONS.md

Paste exactly:

START NEW CHAT
"@

$PacketPath = Join-Path $OutRoot "START-NEW-CHAT-PACKET.md"
Set-Content -Path $PacketPath -Value $Packet -Encoding UTF8

Get-ChildItem $OutRoot | Select-Object Name, Length, LastWriteTime | Sort-Object Name
