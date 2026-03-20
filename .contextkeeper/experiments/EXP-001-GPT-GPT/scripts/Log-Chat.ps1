param(
    [string]$LogType = "CONTROLLER_COMPARISON",
    [string]$ControllerConnectorState = "MIXED",
    [string]$TargetConnectorState = "N/A",
    [string]$BestResponse = "R4",
    [string]$Ranking = "R4>R1>R2>R3",
    [string]$Notes = "Controller connector ON outperformed OFF; main remaining defect is pending-vs-completed run ambiguity."
)

$ErrorActionPreference = "Stop"

$RepoRoot = "C:\Users\Steven\contextkeeper-site"
$ExpRoot = Join-Path $RepoRoot ".contextkeeper\experiments\EXP-001-GPT-GPT"
$LogChatRoot = Join-Path $ExpRoot "log-chat"

$CsvPath = Join-Path $LogChatRoot "LOG-CHAT-RESULTS.csv"
$MdPath = Join-Path $LogChatRoot "LOG-CHAT-RESULTS.md"

New-Item -ItemType Directory -Path $LogChatRoot -Force | Out-Null

if (-not (Test-Path $CsvPath)) {
    "timestamp_local,log_type,controller_connector_state,target_connector_state,best_response,ranking,notes" |
        Set-Content -Path $CsvPath -Encoding UTF8
}

if (-not (Test-Path $MdPath)) {
    Set-Content -Path $MdPath -Value "# LOG-CHAT-RESULTS`r`n" -Encoding UTF8
}

$Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

$SafeFields = @(
    $Timestamp,
    $LogType,
    $ControllerConnectorState,
    $TargetConnectorState,
    $BestResponse,
    $Ranking,
    $Notes
) | ForEach-Object {
    '"' + (($_ -replace '"','""')) + '"'
}

($SafeFields -join ",") | Add-Content -Path $CsvPath -Encoding UTF8

$MdBlock = @"
## $Timestamp

- log_type: $LogType
- controller_connector_state: $ControllerConnectorState
- target_connector_state: $TargetConnectorState
- best_response: $BestResponse
- ranking: $Ranking
- notes: $Notes

"@
Add-Content -Path $MdPath -Value $MdBlock -Encoding UTF8

Set-Location $RepoRoot

$StagePaths = @(
    ".contextkeeper",
    "HANDOFF.md",
    "docs\architecture",
    "docs\whitepapers",
    "app\schema.sql",
    "app\api\v1\index.php",
    "app\api\v1\governance.php",
    "app\api\v1\governance",
    "app\lib\UUID.php",
    "scripts"
)

$ExistingStagePaths = $StagePaths | Where-Object { Test-Path (Join-Path $RepoRoot $_) }
if ($ExistingStagePaths.Count -gt 0) {
    git add -- $ExistingStagePaths
}

$HasStaged = git diff --cached --name-only
if ($HasStaged) {
    git commit -m "EXP-001: log chat result and sync trigger surfaces"
    git push origin main
} else {
    Write-Host "No staged changes to commit or push."
}

Write-Host ""
Write-Host "LOGGED CHAT RESULT"
Write-Host "CSV: $CsvPath"
Write-Host "MD:  $MdPath"
Write-Host "TYPE: $LogType"
Write-Host "BEST: $BestResponse"
Write-Host "RANK: $Ranking"
