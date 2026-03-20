param(
    [Parameter(Mandatory = $true)]
    [string]$RunId,

    [Parameter(Mandatory = $true)]
    [ValidateSet("PASS","FAIL")]
    [string]$Result,

    [string]$PrimaryFailureClass = "",
    [string]$SecondaryFailureClass = "",
    [string]$RuleFailures = "",
    [string]$Notes = ""
)

$ErrorActionPreference = "Stop"

$RepoRoot = "C:\Users\Steven\contextkeeper-site"
$ExpRoot = Join-Path $RepoRoot ".contextkeeper\experiments\EXP-001-GPT-GPT"
$RunsRoot = Join-Path $ExpRoot "runs"
$LedgerPath = Join-Path $ExpRoot "RUN-LEDGER.csv"
$RunRoot = Join-Path $RunsRoot $RunId
$ResponseFile = Join-Path $RunRoot "fresh-chat-response.txt"
$MetaFile = Join-Path $RunRoot "run-metadata.json"

if (-not (Test-Path $RunRoot)) {
    throw "Run folder not found: $RunRoot"
}

$ClipboardText = Get-Clipboard -Raw
if ([string]::IsNullOrWhiteSpace($ClipboardText)) {
    throw "Clipboard is empty. Copy the raw GPT response first."
}

Set-Content -Path $ResponseFile -Value $ClipboardText -Encoding UTF8

$Meta = Get-Content $MetaFile -Raw | ConvertFrom-Json
$Meta.result = $Result
$Meta.status = "COMPLETED"
$Meta.primary_failure_class = $PrimaryFailureClass
$Meta.secondary_failure_class = $SecondaryFailureClass
$Meta.rule_failures = $RuleFailures
$Meta.notes = $Notes
$Meta.completed_timestamp_local = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$Meta | ConvertTo-Json -Depth 5 | Set-Content -Path $MetaFile -Encoding UTF8

$Rows = Import-Csv $LedgerPath
foreach ($Row in $Rows) {
    if ($Row.run_id -eq $RunId) {
        $Row.result = $Result
        $Row.status = "COMPLETED"
        $Row.rule_failures = $RuleFailures
        $CombinedNotes = @()
        if ($PrimaryFailureClass) { $CombinedNotes += "PRIMARY=$PrimaryFailureClass" }
        if ($SecondaryFailureClass) { $CombinedNotes += "SECONDARY=$SecondaryFailureClass" }
        if ($Notes) { $CombinedNotes += $Notes }
        $Row.notes = ($CombinedNotes -join " | ")
    }
}
$Rows | Export-Csv -Path $LedgerPath -NoTypeInformation -Encoding UTF8

Write-Host ""
Write-Host "RUN COMPLETED: $RunId"
Write-Host "Response saved to: $ResponseFile"
Write-Host "Result: $Result"
if ($PrimaryFailureClass) {
    Write-Host "Primary failure: $PrimaryFailureClass"
}
if ($SecondaryFailureClass) {
    Write-Host "Secondary failure: $SecondaryFailureClass"
}
Write-Host ""
Write-Host "Ledger updated: $LedgerPath"

