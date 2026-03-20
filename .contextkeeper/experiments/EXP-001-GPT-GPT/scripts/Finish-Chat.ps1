param(
    [Parameter(Mandatory = $true)]
    [string]$RunId
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
if (-not (Test-Path $MetaFile)) {
    throw "Run metadata not found: $MetaFile"
}

$ClipboardText = Get-Clipboard -Raw
if ([string]::IsNullOrWhiteSpace($ClipboardText)) {
    throw "Clipboard is empty. Copy the full raw target-chat response first."
}

$Meta = Get-Content $MetaFile -Raw | ConvertFrom-Json
$Transport = [string]$Meta.transport_condition

Set-Content -Path $ResponseFile -Value $ClipboardText -Encoding UTF8

$Response = $ClipboardText.Trim()
$PrimaryFailureClass = ""
$SecondaryFailureClass = ""
$RuleFailures = ""
$Notes = ""
$Result = "PASS"
$Confidence = "HIGH"

function Has-LineLike {
    param(
        [string]$Text,
        [string]$Pattern
    )
    return [regex]::IsMatch($Text, $Pattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
}

$HasConfirmedState = Has-LineLike -Text $Response -Pattern '(^|\n)\s*(1\.\s*)?Confirmed state\b'
$HasActiveTask = Has-LineLike -Text $Response -Pattern '(^|\n)\s*(2\.\s*)?Active task\b'
$HasConstraints = Has-LineLike -Text $Response -Pattern '(^|\n)\s*(3\.\s*)?Constraints\b'
$HasUnknowns = Has-LineLike -Text $Response -Pattern '(^|\n)\s*(4\.\s*)?Unknowns\b'
$HasFirstAction = Has-LineLike -Text $Response -Pattern '(^|\n)\s*(5\.\s*)?First action you will take\b'

$MissingSections = @()
if (-not $HasConfirmedState) { $MissingSections += "Confirmed state" }
if (-not $HasActiveTask) { $MissingSections += "Active task" }
if (-not $HasConstraints) { $MissingSections += "Constraints" }
if (-not $HasUnknowns) { $MissingSections += "Unknowns" }
if (-not $HasFirstAction) { $MissingSections += "First action you will take" }

$HasStaleRun003 = $Response -match '\bRUN-003\b'
$HasStaleRun004 = $Response -match '\bRUN-004\b'
$HasRunReference = $Response -match '\bRUN-\d{3}\b'
$HasRepoVerificationDrift = $Response -match 'enumerate and verify repository presence|repository presence|connector evidence gathered|retrieve direct connector evidence'
$HasUnsupportedControllerDrift = $Response -match 'controller handoff packet|latest active run|controller state'
$HasNoRequiredArtifactFound = $Response -match 'required artifact cannot be found|cannot be found'
$LooksTruncated = ($Response.Length -lt 40)

if ($LooksTruncated) {
    $Result = "FAIL"
    $PrimaryFailureClass = "EMPTY_OR_TRUNCATED_RESPONSE"
    $SecondaryFailureClass = "LOW_INFORMATION_OUTPUT"
    $RuleFailures = "Target response was empty or too short to evaluate"
    $Notes = "Response text too short for controlled bootstrap validation."
}
elseif ($MissingSections.Count -gt 0) {
    $Result = "FAIL"
    $PrimaryFailureClass = "BOOTSTRAP_SCHEMA_DRIFT"
    $SecondaryFailureClass = "MISSING_REQUIRED_SECTIONS"
    $RuleFailures = "Missing required sections: " + ($MissingSections -join ", ")
    $Notes = "Target response did not match the required five-section bootstrap format."
}
elseif ($Transport -eq "GITHUB_CONNECTOR_ONLY" -and ($HasStaleRun003 -or $HasStaleRun004 -or $HasUnsupportedControllerDrift)) {
    $Result = "FAIL"
    $PrimaryFailureClass = "CONNECTOR_SCOPE_DRIFT"
    $SecondaryFailureClass = "STALE_RUN_CONTEXT"
    $RuleFailures = "Connector-only response anchored to stale run/controller context instead of remaining tightly bounded to repository-grounded bootstrap output"
    $Notes = "Connector-only target response referenced stale run/controller context."
}
elseif ($Transport -eq "GITHUB_CONNECTOR_ONLY" -and $HasRepoVerificationDrift) {
    $Result = "FAIL"
    $PrimaryFailureClass = "CONNECTOR_SCOPE_DRIFT"
    $SecondaryFailureClass = "REPOSITORY_VERIFICATION_OVERREACH"
    $RuleFailures = "Connector-only response drifted into repository verification behavior instead of constrained initialization output"
    $Notes = "Response overexpanded into repository verification rather than minimal bootstrap behavior."
}
elseif ($Transport -eq "PROMPT_ATTACHMENT" -and $HasRunReference) {
    $Confidence = "MEDIUM"
    $Notes = "Response passed structural checks but referenced run identifiers; review only if this becomes problematic."
}
elseif ($Transport -eq "HYBRID" -and $HasRepoVerificationDrift) {
    $Confidence = "MEDIUM"
    $Notes = "Hybrid response passed structural checks but expanded into repository verification language."
}
else {
    $Result = "PASS"
    $PrimaryFailureClass = ""
    $SecondaryFailureClass = ""
    $RuleFailures = ""
    $Notes = "Auto-classified PASS from structural and mode-specific checks."
}

$Meta.result = $Result
$Meta.status = "COMPLETED"
$Meta.primary_failure_class = $PrimaryFailureClass
$Meta.secondary_failure_class = $SecondaryFailureClass
$Meta.rule_failures = $RuleFailures
$Meta.notes = $Notes
$Meta.classification_confidence = $Confidence
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
        if ($Confidence) { $CombinedNotes += "CONFIDENCE=$Confidence" }
        if ($Notes) { $CombinedNotes += $Notes }
        $Row.notes = ($CombinedNotes -join " | ")
    }
}

$Rows | Export-Csv -Path $LedgerPath -NoTypeInformation -Encoding UTF8

Write-Host ""
Write-Host "RUN COMPLETED: $RunId"
Write-Host "AUTO RESULT: $Result"
if ($PrimaryFailureClass) { Write-Host "PRIMARY FAILURE CLASS: $PrimaryFailureClass" }
if ($SecondaryFailureClass) { Write-Host "SECONDARY FAILURE CLASS: $SecondaryFailureClass" }
if ($RuleFailures) { Write-Host "RULE FAILURES: $RuleFailures" }
Write-Host "CONFIDENCE: $Confidence"
Write-Host "Response saved to: $ResponseFile"
Write-Host "Ledger updated: $LedgerPath"
