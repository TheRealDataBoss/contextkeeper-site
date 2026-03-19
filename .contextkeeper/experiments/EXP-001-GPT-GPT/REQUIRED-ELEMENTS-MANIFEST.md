# REQUIRED-ELEMENTS-MANIFEST.md

## Experiment
- Experiment ID: EXP-001-GPT-GPT
- Scope: GPT-to-GPT handoff validation only

## Required Core Control Files
- .contextkeeper/INITIALIZATION-PROMPT.md
- .contextkeeper/ACCEPTANCE-CHECKLIST.md
- .contextkeeper/INIT-RESPONSE-SCHEMA.md
- .contextkeeper/REJECTION-RULES.md
- .contextkeeper/ORCHESTRATION-PROTOCOL.md

## Required Governance / State Files
- HANDOFF.md
- docs/whitepapers/INDEX.md
- docs/architecture/governance-mapping.md
- docs/architecture/task-governance-v2.md
- docs/architecture/model-registry-spec.md

## Required Application Files
- app/schema.sql
- app/api/v1/index.php
- app/api/v1/governance.php
- app/api/v1/governance/tasks.php
- app/api/v1/governance/contracts.php
- app/api/v1/governance/source.php
- app/api/v1/governance/gates.php
- app/lib/UUID.php
