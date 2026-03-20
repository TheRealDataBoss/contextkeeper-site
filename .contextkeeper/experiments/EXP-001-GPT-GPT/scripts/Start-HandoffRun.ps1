param(
    [Parameter(Mandatory = $true)]
    [ValidateSet("ATTACHMENT_ZIP_ONLY","ATTACHMENT_DIRECT","GITHUB_CONNECTOR_ONLY")]
    [string]$TransportCondition,

    [string]$TargetModel = "GPT-5.4 Thinking",
    [string]$Phase = "INITIALIZATION"
)

$ErrorActionPreference = "Stop"

$RepoRoot = "C:\Users\Steven\contextkeeper-site"
$ExpRoot = Join-Path $RepoRoot ".contextkeeper\experiments\EXP-001-GPT-GPT"
$RunsRoot = Join-Path $ExpRoot "runs"
$PktRoot = Join-Path $ExpRoot "handoff-packets\PKT-001"
$PktZip = Join-Path $ExpRoot "handoff-packets\PKT-001.zip"
$LedgerPath = Join-Path $ExpRoot "RUN-LEDGER.csv"
$PromptTemplatePath = Join-Path $ExpRoot "NEXT-CHAT-BOOTSTRAP-PROMPT.txt"

if (-not (Test-Path $RunsRoot)) {
    New-Item -ItemType Directory -Path $RunsRoot -Force | Out-Null
}

$ExistingRunIds = @()
if (Test-Path $LedgerPath) {
    $Csv = Import-Csv $LedgerPath
    if ($Csv) {
        $ExistingRunIds = $Csv.run_id
    }
}

$MaxRunNumber = 0
foreach ($RunId in $ExistingRunIds) {
    if ($RunId -match '^RUN-(\d{3})$') {
        $Num = [int]$Matches[1]
        if ($Num -gt $MaxRunNumber) {
            $MaxRunNumber = $Num
        }
    }
}

$NextRunNumber = $MaxRunNumber + 1
$RunId = "RUN-{0:d3}" -f $NextRunNumber
$RunRoot = Join-Path $RunsRoot $RunId
New-Item -ItemType Directory -Path $RunRoot -Force | Out-Null

$Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$TimestampSafe = Get-Date -Format "yyyyMMdd-HHmmss"

$RepoCommit = ""
try {
    Push-Location $RepoRoot
    $RepoCommit = (git rev-parse HEAD).Trim()
    Pop-Location
} catch {
    try { Pop-Location } catch {}
    $RepoCommit = ""
}

$ArtifactVersion = "EXP001-SET-001"
$PromptVersion = "BOOTSTRAP-v1"
$FreshChat = "TRUE"

$PromptText = Get-Content $PromptTemplatePath -Raw
Set-Clipboard -Value $PromptText

$PromptFile = Join-Path $RunRoot "bootstrap-prompt.txt"
$ResponseFile = Join-Path $RunRoot "fresh-chat-response.txt"
$MetaFile = Join-Path $RunRoot "run-metadata.json"
$UploadListFile = Join-Path $RunRoot "upload-list.txt"

Set-Content -Path $PromptFile -Value $PromptText -Encoding UTF8
Set-Content -Path $ResponseFile -Value "" -Encoding UTF8

$UploadFiles = @()
switch ($TransportCondition) {
    "ATTACHMENT_ZIP_ONLY" {
        $UploadFiles = @($PktZip)
    }
    "ATTACHMENT_DIRECT" {
        $UploadFiles = Get-ChildItem $PktRoot -File | Sort-Object Name | ForEach-Object { $_.FullName }
    }
    "GITHUB_CONNECTOR_ONLY" {
        $UploadFiles = @()
    }
}

$UploadFilesText = if ($UploadFiles.Count -gt 0) { $UploadFiles -join [Environment]::NewLine } else { "[NO FILE UPLOADS REQUIRED]" }
Set-Content -Path $UploadListFile -Value $UploadFilesText -Encoding UTF8

$Meta = [ordered]@{
    run_id = $RunId
    timestamp_local = $Timestamp
    timestamp_safe = $TimestampSafe
    phase = $Phase
    transport_condition = $TransportCondition
    target_model = $TargetModel
    fresh_chat = $FreshChat
    artifact_set_version = $ArtifactVersion
    prompt_version = $PromptVersion
    repo_commit_sha = $RepoCommit
    prompt_file = $PromptFile
    response_file = $ResponseFile
    upload_list_file = $UploadListFile
    result = "PENDING"
    status = "STARTED"
    primary_failure_class = ""
    secondary_failure_class = ""
    notes = ""
}
$Meta | ConvertTo-Json -Depth 5 | Set-Content -Path $MetaFile -Encoding UTF8

if (-not (Test-Path $LedgerPath)) {
    'run_id,experiment_id,timestamp_local,phase,transport_condition,source_chat_family,target_chat_family,target_model,fresh_chat,artifact_set_version,prompt_version,repo_ref,result,status,rule_failures,notes' |
        Set-Content -Path $LedgerPath -Encoding UTF8
}

$Row = [PSCustomObject]@{
    run_id = $RunId
    experiment_id = "EXP-001-GPT-GPT"
    timestamp_local = $Timestamp
    phase = $Phase
    transport_condition = $TransportCondition
    source_chat_family = "GPT"
    target_chat_family = "GPT"
    target_model = $TargetModel
    fresh_chat = $FreshChat
    artifact_set_version = $ArtifactVersion
    prompt_version = $PromptVersion
    repo_ref = $RepoCommit
    result = "PENDING"
    status = "STARTED"
    rule_failures = ""
    notes = ""
}

$Existing = @()
try {
    $Existing = Import-Csv $LedgerPath
} catch {
    $Existing = @()
}
@($Existing + $Row) | Export-Csv -Path $LedgerPath -NoTypeInformation -Encoding UTF8

Write-Host ""
Write-Host "RUN CREATED: $RunId"
Write-Host "Run folder: $RunRoot"
Write-Host "Transport: $TransportCondition"
Write-Host "Repo commit: $RepoCommit"
Write-Host ""
Write-Host "Bootstrap prompt copied to clipboard."
Write-Host "Prompt file: $PromptFile"
Write-Host "Upload list: $UploadListFile"
Write-Host "Response target: $ResponseFile"
Write-Host ""
Write-Host "NEXT ACTIONS:"
if ($TransportCondition -eq "ATTACHMENT_ZIP_ONLY") {
    Write-Host "1. Open a fresh GPT chat."
    Write-Host "2. Upload PKT-001.zip."
    Write-Host "3. Paste clipboard contents into the chat input."
    Write-Host "4. Copy the raw response to clipboard."
    Write-Host "5. Run Complete-HandoffRun.ps1 with this run id."
} elseif ($TransportCondition -eq "ATTACHMENT_DIRECT") {
    Write-Host "1. Open a fresh GPT chat."
    Write-Host "2. Upload all files listed in upload-list.txt."
    Write-Host "3. Paste clipboard contents into the chat input."
    Write-Host "4. Copy the raw response to clipboard."
    Write-Host "5. Run Complete-HandoffRun.ps1 with this run id."
} else {
    Write-Host "1. Open a fresh GPT chat with GitHub connector available."
    Write-Host "2. Do not upload packet files."
    Write-Host "3. Paste clipboard contents into the chat input."
    Write-Host "4. Copy the raw response to clipboard."
    Write-Host "5. Run Complete-HandoffRun.ps1 with this run id."
}

Start-Process explorer.exe $RunRoot
