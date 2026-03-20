$RepoRoot = "C:\Users\Steven\contextkeeper-site"
$ExpRoot = Join-Path $RepoRoot ".contextkeeper\experiments\EXP-001-GPT-GPT"

$RequiredPaths = @(
    $ExpRoot,
    (Join-Path $ExpRoot "runs"),
    (Join-Path $ExpRoot "evidence"),
    (Join-Path $ExpRoot "logs"),
    (Join-Path $ExpRoot "handoff-packets"),
    (Join-Path $ExpRoot "handoff-packets\PKT-001"),
    (Join-Path $ExpRoot "scripts")
)

foreach ($Path in $RequiredPaths) {
    New-Item -ItemType Directory -Path $Path -Force | Out-Null
}

$RequiredFiles = @(
    ".contextkeeper\INITIALIZATION-PROMPT.md",
    ".contextkeeper\ACCEPTANCE-CHECKLIST.md",
    ".contextkeeper\INIT-RESPONSE-SCHEMA.md",
    ".contextkeeper\REJECTION-RULES.md",
    ".contextkeeper\ORCHESTRATION-PROTOCOL.md",
    "HANDOFF.md",
    "docs\whitepapers\INDEX.md",
    "docs\architecture\governance-mapping.md",
    "docs\architecture\task-governance-v2.md",
    "docs\architecture\model-registry-spec.md",
    "app\schema.sql",
    "app\api\v1\index.php",
    "app\api\v1\governance.php",
    "app\api\v1\governance\tasks.php",
    "app\api\v1\governance\contracts.php",
    "app\api\v1\governance\source.php",
    "app\api\v1\governance\gates.php",
    "app\lib\UUID.php",
    ".contextkeeper\experiments\EXP-001-GPT-GPT\RUN-LEDGER.csv",
    ".contextkeeper\experiments\EXP-001-GPT-GPT\ARTIFACT-VERSIONS.csv",
    ".contextkeeper\experiments\EXP-001-GPT-GPT\REQUIRED-ELEMENTS-MANIFEST.md",
    ".contextkeeper\experiments\EXP-001-GPT-GPT\NEXT-CHAT-BOOTSTRAP-PROMPT.txt",
    ".contextkeeper\experiments\EXP-001-GPT-GPT\FAILURE-TAXONOMY.md"
)

Write-Host ""
Write-Host "Handoff system paths checked."
Write-Host "Required file count expected: $($RequiredFiles.Count)"
Write-Host ""
