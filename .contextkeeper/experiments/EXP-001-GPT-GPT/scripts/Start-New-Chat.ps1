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
$NewAttachmentRunCommand = "start-new-chat-experiment-attachment"
$NewGitHubRunCommand = "start-new-chat-experiment-github"
$NewHybridRunCommand = "start-new-chat-experiment-hybrid"

$AllRuns = @()
if (Test-Path $RunsRoot) {
    $AllRuns = @(Get-ChildItem $RunsRoot -Directory | Sort-Object Name)
}

$LatestRun = if ($AllRuns.Count -gt 0) { $AllRuns[-1] } else { $null }
$LatestRunName = if ($LatestRun) { $LatestRun.Name } else { "NONE" }
$LatestRunRoot = if ($LatestRun) { $LatestRun.FullName } else { "" }

$PendingRuns = @()
$CompletedRuns = @()

foreach ($RunDir in $AllRuns) {
    $MetaPath = Join-Path $RunDir.FullName "run-metadata.json"
    if (Test-Path $MetaPath) {
        try {
            $Meta = Get-Content $MetaPath -Raw | ConvertFrom-Json
            $Status = [string]$Meta.status
            $Result = [string]$Meta.result
            $Obj = [PSCustomObject]@{
                RunDir = $RunDir
                Meta = $Meta
            }
            if ($Status -eq "STARTED" -and $Result -eq "PENDING") {
                $PendingRuns += $Obj
            }
            elseif ($Status -eq "COMPLETED") {
                $CompletedRuns += $Obj
            }
        } catch {
        }
    }
}

$LatestPending = if ($PendingRuns.Count -gt 0) { ($PendingRuns | Sort-Object { $_.RunDir.Name })[-1] } else { $null }
$LatestCompleted = if ($CompletedRuns.Count -gt 0) { ($CompletedRuns | Sort-Object { $_.RunDir.Name })[-1] } else { $null }

$SelectedRun = if ($LatestPending) { $LatestPending } else { $LatestCompleted }
$SelectedRunName = if ($SelectedRun) { $SelectedRun.RunDir.Name } else { "NONE" }
$SelectedRunRoot = if ($SelectedRun) { $SelectedRun.RunDir.FullName } else { "" }

$MetaObj = if ($SelectedRun) { $SelectedRun.Meta } else { $null }

$TransportCondition = if ($MetaObj -and $MetaObj.transport_condition) { [string]$MetaObj.transport_condition } else { "" }
$CommandRoot = if ($MetaObj -and $MetaObj.command_root) { [string]$MetaObj.command_root } else { "" }
$UploadPacketRoot = if ($MetaObj -and $MetaObj.upload_packet_root) { [string]$MetaObj.upload_packet_root } else { "" }
$CurrentPromptPath = if ($MetaObj -and $MetaObj.current_prompt_path) { [string]$MetaObj.current_prompt_path } else { "" }
$RunStatus = if ($MetaObj -and $MetaObj.status) { [string]$MetaObj.status } else { "" }
$RunResult = if ($MetaObj -and $MetaObj.result) { [string]$MetaObj.result } else { "" }
$RunTimestamp = if ($MetaObj -and $MetaObj.timestamp_local) { [string]$MetaObj.timestamp_local } else { "" }
$ResponseTargetFile = if ($SelectedRun) { Join-Path $SelectedRun.RunDir.FullName "fresh-chat-response.txt" } else { "" }

$LatestPendingRunId = if ($LatestPending) { $LatestPending.RunDir.Name } else { "" }
$LatestCompletedRunId = if ($LatestCompleted) { $LatestCompleted.RunDir.Name } else { "" }

$HasPendingRun = $false
if ($LatestPending) { $HasPendingRun = $true }

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
    $LedgerPreview = (Get-Content $LedgerPath | Select-Object -Last 8) -join [Environment]::NewLine
}

$ArtifactPreview = ""
if (Test-Path $ArtifactPath) {
    $ArtifactPreview = (Get-Content $ArtifactPath | Select-Object -First 10) -join [Environment]::NewLine
}

$NewRunInstruction = @"
No pending run currently exists.

Start a new run first in PowerShell using exactly one of:

$NewAttachmentRunCommand
$NewGitHubRunCommand
$NewHybridRunCommand

After the new run is created, use start-new-chat again so the controller packet refreshes to that new pending run.
"@

$TargetInstruction = ""
if ($HasPendingRun) {
    $TargetInstruction = switch ($TransportCondition) {
        "PROMPT_ATTACHMENT" {
@"
A pending run exists: $SelectedRunName

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
A pending run exists: $SelectedRunName

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
A pending run exists: $SelectedRunName

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
A pending run exists: $SelectedRunName

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
} else {
    $TargetInstruction = $NewRunInstruction
}

$CurrentExperimentStatus = ""
if ($HasPendingRun) {
    $CurrentExperimentStatus = @"
A pending run exists and is ready for execution.

Pending run ID: $SelectedRunName
Transport condition: $TransportCondition
Status: $RunStatus
Result: $RunResult

The next controller action is to execute the pending target-chat run.
"@
} else {
    $CurrentExperimentStatus = @"
No pending run currently exists.

Latest completed run: $LatestCompletedRunId

The next controller action is to create a new run before any target-chat execution begins.
"@
}

$LatestCompletedAction = ""
if ($LatestCompleted) {
    $CompletedMeta = $LatestCompleted.Meta
    $CompletedNotes = if ($CompletedMeta.notes) { [string]$CompletedMeta.notes } else { "" }
    $CompletedRuleFailures = if ($CompletedMeta.rule_failures) { [string]$CompletedMeta.rule_failures } else { "" }
    $CompletedConfidence = if ($CompletedMeta.classification_confidence) { [string]$CompletedMeta.classification_confidence } else { "" }

    $LatestCompletedAction = @"
Latest completed run: $LatestCompletedRunId

Completed run folder:
$($LatestCompleted.RunDir.FullName)

Result: $($CompletedMeta.result)
Status: $($CompletedMeta.status)
Completed timestamp: $($CompletedMeta.completed_timestamp_local)

Rule failures:
$CompletedRuleFailures

Notes:
$CompletedNotes

Classification confidence:
$CompletedConfidence
"@
} else {
    $LatestCompletedAction = "No completed run exists yet."
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
Prefer the latest pending run for operator instructions.
If no pending run exists, explicitly instruct the operator to create a new run first.
Do not describe a completed run as active or executable.
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

If no pending run exists, the controller must instruct the operator to create a new run first.
"@
Set-Content -Path $ContinuityPath -Value $Continuity -Encoding UTF8

$PostResponseInstruction = if ($HasPendingRun) {
@"
Copy the full raw target-chat response to your clipboard.

Open PowerShell.

Run exactly:

$FinishChatCommand
"@
} else {
@"
Do not run finish-chat yet.

There is no pending run to close.

First create a new run in PowerShell using one of:

$NewAttachmentRunCommand
$NewGitHubRunCommand
$NewHybridRunCommand
"@
}

$Packet = @"
# START-NEW-CHAT-PACKET.md

## Current Experiment
- Experiment ID: EXP-001-GPT-GPT
- Repo commit: $RepoCommit

## Current Authority
- Whitepaper: $WhitepaperPath
- Ledger: $LedgerPath
- Artifact versions: $ArtifactPath

## Run State Summary
- Latest run ID: $LatestRunName
- Latest pending run ID: $LatestPendingRunId
- Latest completed run ID: $LatestCompletedRunId
- Pending run exists: $HasPendingRun

## Selected Controller Focus
- Run ID: $SelectedRunName
- Run folder: $SelectedRunRoot
- Transport condition: $TransportCondition
- Status: $RunStatus
- Result: $RunResult
- Timestamp local: $RunTimestamp
- Command root: $CommandRoot
- Upload packet root: $UploadPacketRoot
- Current prompt path: $CurrentPromptPath
- Response target file: $ResponseTargetFile

## Current Experiment Status
$CurrentExperimentStatus

## Latest Completed Action
$LatestCompletedAction

## Ledger Tail
$LedgerPreview

## Artifact Version Preview
$ArtifactPreview

## Operator-Ready Next Action
$TargetInstruction

## Exact Post-Response Logging Instruction
$PostResponseInstruction

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
