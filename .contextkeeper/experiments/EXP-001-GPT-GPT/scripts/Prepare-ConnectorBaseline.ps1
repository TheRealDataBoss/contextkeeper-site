$ErrorActionPreference = "Stop"

$RepoRoot = "C:\Users\Steven\contextkeeper-site"
$ExpRoot = Join-Path $RepoRoot ".contextkeeper\experiments\EXP-001-GPT-GPT"

Push-Location $RepoRoot
$RepoCommit = (git rev-parse HEAD).Trim()
Pop-Location

$ConnectorInstruction = @"
Use GitHub connector only.
Repository: TheRealDataBoss/contextkeeper-site
Pinned repo commit: $RepoCommit

Read these repo files in this order:
1. .contextkeeper/INITIALIZATION-PROMPT.md
2. .contextkeeper/ACCEPTANCE-CHECKLIST.md
3. .contextkeeper/INIT-RESPONSE-SCHEMA.md
4. .contextkeeper/REJECTION-RULES.md
5. .contextkeeper/ORCHESTRATION-PROTOCOL.md
6. HANDOFF.md
7. docs/whitepapers/INDEX.md
8. docs/architecture/governance-mapping.md
9. docs/architecture/task-governance-v2.md
10. docs/architecture/model-registry-spec.md
11. app/schema.sql
12. app/api/v1/index.php
13. app/api/v1/governance.php
14. app/api/v1/governance/tasks.php
15. app/api/v1/governance/contracts.php
16. app/api/v1/governance/source.php
17. app/api/v1/governance/gates.php
18. app/lib/UUID.php

Do not use prior chat history.
Do not use prompt-box file attachments.
Do not assume anything not present in repo files at the pinned commit.
"@

$OutFile = Join-Path $ExpRoot "GITHUB-CONNECTOR-INSTRUCTIONS.txt"
Set-Content -Path $OutFile -Value $ConnectorInstruction -Encoding UTF8
Set-Clipboard -Value $ConnectorInstruction

Write-Host "Connector instructions written to: $OutFile"
Write-Host "Connector instructions copied to clipboard."
Write-Host "Pinned commit: $RepoCommit"

