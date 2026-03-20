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
$FinishScriptPath = Join-Path $ScriptsRoot "Finish-Chat.ps1"
$ControllerScriptPath = Join-Path $ScriptsRoot "Start-New-Chat.ps1"
$RunScriptPath = Join-Path $ScriptsRoot "Start-New-Chat-Experiment.ps1"

$LatestRun = Get-ChildItem $RunsRoot -Directory | Sort-Object Name | Select-Object -Last 1
$LatestRunName = if ($LatestRun) { $LatestRun.Name } else { "NONE" }
$LatestRunRoot = if ($LatestRun) { $LatestRun.FullName } else { "" }

$LatestRunMetaPath = if ($LatestRun) { Join-Path $LatestRunRoot "run-metadata.json" } else { "" }
$LatestRunResponsePath = if ($LatestRun) { Join-Path $LatestRunRoot "fresh-chat-response.txt" } else { "" }

$LatestRunMetaText = if ($LatestRunMetaPath -and (Test-Path $LatestRunMetaPath)) { Get-Content $LatestRunMetaPath -Raw } else { "{}" }

$UploadPacketRoot = ""
$CommandRoot = ""
$CurrentBootstrapPrompt = ""

try {
    if ($LatestRunMetaPath -and (Test-Path $LatestRunMetaPath)) {
        $MetaObj = Get-Content $LatestRunMetaPath -Raw | ConvertFrom-Json
        if ($null -ne $MetaObj.upload_packet_root) {
            $UploadPacketRoot = [string]$MetaObj.upload_packet_root
        }
        if ($null -ne $MetaObj.command_root) {
            $CommandRoot = [string]$MetaObj.command_root
        }
        if ($null -ne $MetaObj.current_bootstrap_prompt) {
            $CurrentBootstrapPrompt = [string]$MetaObj.current_bootstrap_prompt
        }
    }
} catch {
    $UploadPacketRoot = ""
    $CommandRoot = ""
    $CurrentBootstrapPrompt = ""
}

$UploadFileNames = @()
if ($UploadPacketRoot -and (Test-Path $UploadPacketRoot)) {
    $UploadFileNames = Get-ChildItem $UploadPacketRoot -File | Sort-Object Name | ForEach-Object { $_.Name }
}

$UploadFileListText = if ($UploadFileNames.Count -gt 0) {
    ($UploadFileNames | ForEach-Object { "- $_" }) -join [Environment]::NewLine
} else {
    "- [UPLOAD FILE LIST UNAVAILABLE]"
}

$LedgerPreview = ""
if (Test-Path $LedgerPath) {
    $LedgerPreview = (Get-Content $LedgerPath | Select-Object -Last 5) -join [Environment]::NewLine
}

$ArtifactPreview = ""
if (Test-Path $ArtifactPath) {
    $ArtifactPreview = (Get-Content $ArtifactPath | Select-Object -First 10) -join [Environment]::NewLine
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
The controller must provide exact absolute paths when they are present in the uploaded files.
Do not replace exact paths with placeholders, ellipses, or summaries.
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

## Latest Active Run
- Run ID: $LatestRunName
- Run folder: $LatestRunRoot
- Command root: $CommandRoot
- Upload packet root: $UploadPacketRoot
- Current bootstrap prompt: $CurrentBootstrapPrompt
- Response target file: $LatestRunResponsePath

## Latest Run Metadata
$LatestRunMetaText

## Ledger Tail
$LedgerPreview

## Artifact Version Preview
$ArtifactPreview

## OPERATOR-READY NEXT ACTION
Use these exact paths and files.

### Open this target-chat command folder
$CommandRoot

### Upload these files from this upload packet folder
$UploadPacketRoot

$UploadFileListText

### Open this local file and copy its full contents exactly
$CurrentBootstrapPrompt

### After the target chat replies, copy the full raw response and run exactly one of:
$FinishScriptPath -RunId $LatestRunName -Result PASS
$FinishScriptPath -RunId $LatestRunName -Result FAIL

## Exact Next Target-Chat Instruction
If the latest run is still STARTED and PENDING:
1. Open a fresh GPT target chat.
2. Go to:
   $CommandRoot
3. Upload all bundled files from:
   $UploadPacketRoot
4. Open this file locally:
   $CurrentBootstrapPrompt
5. Copy the CONTENTS of that file.
6. Paste those CONTENTS into the fresh target chat exactly.
7. Send it.
8. Copy the full raw target-chat response.
9. Return to the controller chat.
10. Run:
    $FinishScriptPath -RunId $LatestRunName -Result PASS
    or
    $FinishScriptPath -RunId $LatestRunName -Result FAIL

## Controller Re-Handoff Instruction
1. Run:
   $ControllerScriptPath
2. Open a fresh GPT controller chat.
3. Go to:
   $OutRoot
4. Upload:
   START-NEW-CHAT-PACKET.md
   START-NEW-CHAT-CONTINUITY.md
   START-NEW-CHAT-INSTRUCTIONS.md
5. Paste:

START NEW CHAT

## Trigger Summary
- To replace the controller, use:
  $ControllerScriptPath
- To start the next target run, use:
  $RunScriptPath
"@

$PacketPath = Join-Path $OutRoot "START-NEW-CHAT-PACKET.md"
Set-Content -Path $PacketPath -Value $Packet -Encoding UTF8

Get-Item $PacketPath, $ContinuityPath, $InstructionsPath | Select-Object Name, Length, LastWriteTime
