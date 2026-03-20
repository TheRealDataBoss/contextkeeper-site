param(
    [Parameter(Mandatory = $true)]
    [string]$RunId,

    [Parameter(Mandatory = $true)]
    [ValidateSet("PASS","FAIL")]
    [string]$Result
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

$PrimaryFailureClass = ""
$SecondaryFailureClass = ""
$RuleFailures = ""
$Notes = ""

if ($Result -eq "FAIL") {
    $PrimaryFailureClass = Read-Host "Primary failure class"
    $SecondaryFailureClass = Read-Host "Secondary failure class (optional)"
    $RuleFailures = Read-Host "Rule failures (optional)"
    $Notes = Read-Host "Notes"
} else {
    $Notes = Read-Host "Notes (optional)"
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

$Rows = @()
if (Test-Path $LedgerPath) {
    $Imported = Import-Csv $LedgerPath
    if ($null -ne $Imported) {
        $Rows = @($Imported)
    }
}

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
Write-Host "Ledger updated: $LedgerPath"
