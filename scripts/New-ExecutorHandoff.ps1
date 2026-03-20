$Repo = "C:\Users\Steven\contextkeeper-site"
$Bundle = "C:\Users\Steven\contextkeeper-executor-bundle"
$Zip = "C:\Users\Steven\contextkeeper-executor-bundle.zip"
$PromptFile = "C:\Users\Steven\contextkeeper-executor-init-prompt.txt"
$ChecklistFile = "C:\Users\Steven\contextkeeper-executor-acceptance-checklist.txt"

if (Test-Path $Bundle) {
    Remove-Item $Bundle -Recurse -Force
}

New-Item -ItemType Directory -Path $Bundle | Out-Null
New-Item -ItemType Directory -Path "$Bundle\.contextkeeper" -Force | Out-Null
New-Item -ItemType Directory -Path "$Bundle\docs\whitepapers" -Force | Out-Null
New-Item -ItemType Directory -Path "$Bundle\docs\architecture" -Force | Out-Null
New-Item -ItemType Directory -Path "$Bundle\app\api\v1\governance" -Force | Out-Null
New-Item -ItemType Directory -Path "$Bundle\app\lib" -Force | Out-Null

Copy-Item "$Repo\.contextkeeper\ORCHESTRATION-PROTOCOL.md" "$Bundle\.contextkeeper\ORCHESTRATION-PROTOCOL.md"
Copy-Item "$Repo\HANDOFF.md" "$Bundle\HANDOFF.md"
Copy-Item "$Repo\docs\whitepapers\INDEX.md" "$Bundle\docs\whitepapers\INDEX.md"
Copy-Item "$Repo\docs\architecture\governance-mapping.md" "$Bundle\docs\architecture\governance-mapping.md"
Copy-Item "$Repo\docs\architecture\task-governance-v2.md" "$Bundle\docs\architecture\task-governance-v2.md"
Copy-Item "$Repo\docs\architecture\model-registry-spec.md" "$Bundle\docs\architecture\model-registry-spec.md"
Copy-Item "$Repo\app\schema.sql" "$Bundle\app\schema.sql"
Copy-Item "$Repo\app\api\v1\index.php" "$Bundle\app\api\v1\index.php"
Copy-Item "$Repo\app\api\v1\governance.php" "$Bundle\app\api\v1\governance.php"
Copy-Item "$Repo\app\api\v1\governance\tasks.php" "$Bundle\app\api\v1\governance\tasks.php"
Copy-Item "$Repo\app\api\v1\governance\contracts.php" "$Bundle\app\api\v1\governance\contracts.php"
Copy-Item "$Repo\app\api\v1\governance\source.php" "$Bundle\app\api\v1\governance\source.php"
Copy-Item "$Repo\app\api\v1\governance\gates.php" "$Bundle\app\api\v1\governance\gates.php"
Copy-Item "$Repo\app\lib\UUID.php" "$Bundle\app\lib\UUID.php"

if (Test-Path $Zip) {
    Remove-Item $Zip -Force
}

Compress-Archive -Path "$Bundle\*" -DestinationPath $Zip

$Prompt = @"
STOP. Fresh initialization only.

You are Claude executor for ContextKeeper.
This is an existing production system.
Do not infer from memory.
Do not write code.
Do not propose changes.
Do not simulate execution.

Authoritative files are attached in canonical structure.

Return exactly:

1. Confirmed current system state
2. Confirmed executor protocol understanding
3. Exact handback format you will return to GPT after execution
4. Any inconsistencies or STOP-WORK blockers

Do not do anything else.
Wait for GPT execution prompt after initialization is accepted.
"@

Set-Content -Path $PromptFile -Value $Prompt -Encoding UTF8

$Checklist = @"
Initialization is accepted only if all of the following are true:

1. Claude explicitly confirms ORCHESTRATION-PROTOCOL.md was read and is governing execution behavior.
2. Claude distinguishes attached source truth from any production inference.
3. Claude provides the exact handback format for GPT state update.
4. Claude identifies schema limitations precisely without overclaiming.
5. Claude does not write code, propose implementation, simulate execution, or claim deployment.
6. Claude does not claim any fact not directly supported by attached files.

If any condition fails, reject initialization and restart from fresh bundle.
"@

Set-Content -Path $ChecklistFile -Value $Checklist -Encoding UTF8

Write-Host ""
Write-Host "Executor handoff bundle created successfully."
Write-Host "Bundle folder:   $Bundle"
Write-Host "Bundle zip:      $Zip"
Write-Host "Init prompt:     $PromptFile"
Write-Host "Acceptance file: $ChecklistFile"
Write-Host ""

Start-Process explorer.exe "/select,$Zip"
