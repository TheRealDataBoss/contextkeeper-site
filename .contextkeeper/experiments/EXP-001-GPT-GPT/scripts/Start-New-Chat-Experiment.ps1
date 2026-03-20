param(
    [ValidateSet("PROMPT_ATTACHMENT","GITHUB_CONNECTOR_ONLY","HYBRID")]
    [string]$Mode = "PROMPT_ATTACHMENT"
)

$ErrorActionPreference = "Stop"

$RepoRoot = "C:\Users\Steven\contextkeeper-site"
$ExpRoot = Join-Path $RepoRoot ".contextkeeper\experiments\EXP-001-GPT-GPT"
$RunsRoot = Join-Path $ExpRoot "runs"
$LedgerPath = Join-Path $ExpRoot "RUN-LEDGER.csv"

$AttachmentRoot = Join-Path $ExpRoot "start-new-chat-experiment\prompt-attachment"
$AttachmentPacketRoot = Join-Path $AttachmentRoot "CURRENT-PACKET"
$AttachmentPromptPath = Join-Path $AttachmentRoot "CURRENT-BOOTSTRAP-PROMPT.txt"

$GitHubRoot = Join-Path $ExpRoot "start-new-chat-experiment\github-connector"
$GitHubPromptPath = Join-Path $GitHubRoot "CURRENT-GITHUB-CONNECTOR-PROMPT.txt"

$HybridRoot = Join-Path $ExpRoot "start-new-chat-experiment\hybrid"
$HybridPromptPath = Join-Path $HybridRoot "CURRENT-HYBRID-PROMPT.txt"

$RequiredPaths = @(
    $LedgerPath,
    $AttachmentPromptPath,
    $GitHubPromptPath,
    $HybridPromptPath
)

if (-not (Test-Path $AttachmentPromptPath)) {
    throw "Missing required prompt attachment prompt: $AttachmentPromptPath"
}
if (-not (Test-Path $GitHubPromptPath)) {
    throw "Missing required GitHub connector prompt: $GitHubPromptPath"
}
if (-not (Test-Path $HybridPromptPath)) {
    throw "Missing required hybrid prompt: $HybridPromptPath"
}

$ExistingRows = @()
if (Test-Path $LedgerPath) {
    try {
        $Imported = Import-Csv $LedgerPath
        if ($null -ne $Imported) {
            $ExistingRows = @($Imported)
        }
    } catch {
        $ExistingRows = @()
    }
}

$ExistingRunIds = @()
foreach ($ExistingRow in $ExistingRows) {
    if ($null -ne $ExistingRow.run_id) {
        $ExistingRunIds += $ExistingRow.run_id
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

Push-Location $RepoRoot
$RepoCommit = (git rev-parse HEAD).Trim()
Pop-Location

$ResponseFile = Join-Path $RunRoot "fresh-chat-response.txt"
$MetaFile = Join-Path $RunRoot "run-metadata.json"
$UploadListFile = Join-Path $RunRoot "upload-list.txt"
$PromptFile = Join-Path $RunRoot "bootstrap-prompt.txt"

$UploadFiles = @()
$CommandRoot = ""
$CurrentPromptPath = ""

switch ($Mode) {
    "PROMPT_ATTACHMENT" {
        $CommandRoot = $AttachmentRoot
        $CurrentPromptPath = $AttachmentPromptPath
        $UploadFiles = @(Get-ChildItem $AttachmentPacketRoot -File | Sort-Object Name | ForEach-Object { $_.FullName })
    }
    "GITHUB_CONNECTOR_ONLY" {
        $CommandRoot = $GitHubRoot
        $CurrentPromptPath = $GitHubPromptPath
        $UploadFiles = @()
    }
    "HYBRID" {
        $CommandRoot = $HybridRoot
        $CurrentPromptPath = $HybridPromptPath
        $UploadFiles = @()
    }
}

if (-not (Test-Path $CurrentPromptPath)) {
    throw "Missing mode-specific prompt: $CurrentPromptPath"
}

$PromptText = Get-Content $CurrentPromptPath -Raw
Set-Clipboard -Value $PromptText
Set-Content -Path $PromptFile -Value $PromptText -Encoding UTF8
Set-Content -Path $ResponseFile -Value "" -Encoding UTF8

$UploadFilesText = if ($UploadFiles.Count -gt 0) { $UploadFiles -join [Environment]::NewLine } else { "[NO FILE UPLOADS REQUIRED]" }
Set-Content -Path $UploadListFile -Value $UploadFilesText -Encoding UTF8

$Meta = [ordered]@{
    run_id = $RunId
    timestamp_local = $Timestamp
    transport_condition = $Mode
    repo_commit_sha = $RepoCommit
    prompt_file = $PromptFile
    current_prompt_path = $CurrentPromptPath
    response_file = $ResponseFile
    upload_list_file = $UploadListFile
    upload_packet_root = $(if ($UploadFiles.Count -gt 0) { Split-Path $UploadFiles[0] -Parent } else { "" })
    command_root = $CommandRoot
    result = "PENDING"
    status = "STARTED"
}
$Meta | ConvertTo-Json -Depth 5 | Set-Content -Path $MetaFile -Encoding UTF8

if (-not (Test-Path $LedgerPath)) {
    'run_id,experiment_id,timestamp_local,phase,transport_condition,source_chat_family,target_chat_family,target_model,fresh_chat,artifact_set_version,prompt_version,repo_ref,result,status,rule_failures,notes' |
        Set-Content -Path $LedgerPath -Encoding UTF8
    $ExistingRows = @()
}

$Row = [PSCustomObject]@{
    run_id = $RunId
    experiment_id = "EXP-001-GPT-GPT"
    timestamp_local = $Timestamp
    phase = "INITIALIZATION"
    transport_condition = $Mode
    source_chat_family = "GPT"
    target_chat_family = "GPT"
    target_model = "GPT-5.4 Thinking"
    fresh_chat = "TRUE"
    artifact_set_version = "EXP001-SET-001"
    prompt_version = "BOOTSTRAP-v1"
    repo_ref = $RepoCommit
    result = "PENDING"
    status = "STARTED"
    rule_failures = ""
    notes = ""
}

$AllRows = @($ExistingRows) + @($Row)
$AllRows | Export-Csv -Path $LedgerPath -NoTypeInformation -Encoding UTF8

Write-Host ""
Write-Host "RUN CREATED: $RunId"
Write-Host "MODE: $Mode"
Write-Host "REPO COMMIT: $RepoCommit"
Write-Host "RUN FOLDER: $RunRoot"
Write-Host "COMMAND ROOT: $CommandRoot"
Write-Host "PROMPT PATH: $CurrentPromptPath"
Write-Host ""
Write-Host "Bootstrap prompt copied to clipboard."
Write-Host "Upload list written to: $UploadListFile"
Write-Host "Upload count: $($UploadFiles.Count)"
Write-Host ""
Write-Host "NEXT OPERATOR ACTION:"
switch ($Mode) {
    "PROMPT_ATTACHMENT" {
        Write-Host "Open a fresh GPT chat, upload the files from $CommandRoot\CURRENT-PACKET, paste the prompt from $CurrentPromptPath, then copy the raw response."
    }
    "GITHUB_CONNECTOR_ONLY" {
        Write-Host "Open a fresh GPT chat with the GitHub connector for TheRealDataBoss/contextkeeper-site, paste the prompt from $CurrentPromptPath, then copy the raw response."
    }
    "HYBRID" {
        Write-Host "Open a fresh GPT chat with the GitHub connector for TheRealDataBoss/contextkeeper-site, paste the prompt from $CurrentPromptPath, then copy the raw response."
    }
}

Start-Process explorer.exe $RunRoot
