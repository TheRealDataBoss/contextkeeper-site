# HANDOFF.md

> ContextKeeper project handoff document
> This file must be read by any new agent, model, or session entering the project.
> Last updated: 2026-03-17 (G0: Governance Bootstrapping)

---

## Project Identity

- Product: ContextKeeper (contextkeeper.org)
- Repository: TheRealDataBoss/contextkeeper-site (GitHub, canonical source)
- Deployment: cPanel at contextkeeper.org (deployment target only)
- Operator: Steven Wazlavek

## Current System State

### Deployed and Verified

- Marketing pages: live
- Authentication system: live (session + API key)
- Stripe billing: live
- Password reset flow: live
- Google OAuth endpoint: exists, pending credential configuration
- Bundle Generation Engine: live
- Connector system: 21 connector classes, 20 types supported by API
- SHAM context event logging: live (context_events table, ContextEvent, ContextLogger, ContextMatcher)
- Normalized scoring: live (_score + _score_normalized on all query results)
- Ephemeral test endpoint: live (zero residual rows)
- Connector cache layer: live (connector_cache table, ConnectorCache class)
- Connector ingestion pipeline: live (fetchCacheRecords on GitHub and LocalFile connectors, POST /connectors/:id/ingest endpoint)
- Ingestion idempotency: verified (dedup via unique index + exists() check)

### Database Tables (Production)

connectors, connector_cache, context_events, decisions, invariants, login_attempts, password_resets, projects, sessions_log, usage_log, users, webhook_events

### Active Connectors

- Connector ID 5: GitHub (TheRealDataBoss/contextkeeper-site), active, 18 cached records
- Connectors 2, 3, 4: defunct (expired tokens), should be deleted

---

## Governance-First Development Rule

**Effective immediately, all development on this project operates under the Enterprise Delivery Governance framework defined in `/docs/whitepapers/enterprise-delivery-governance-v4.md`.**

### Non-Negotiable Rules

1. **Every execution prompt must include an Enterprise Delivery Contract.** The orchestrator must populate all 10 contract fields (system invariants, operational assumptions, security requirements, failure mode requirements, observability requirements, performance constraints, idempotency requirements, migration/rollback plan, verification evidence required, review failure criteria). If the contract is missing, the executor must request it before proceeding.

2. **Every handback must pass all four Quality Gates.** Build Gate (no placeholders, no speculative requires, all files complete). Proof Gate (concrete evidence, not claims). Operations Gate (error handling, logging, idempotency). Architecture Gate (extensible, no forced future rebuilds). Gate failures cause handback rejection.

3. **The Source Truth Guarantee is a hard stop-work condition.** If the executor needs to modify or integrate with an existing file and that file is not attached to the prompt, the executor must halt and request it. No reconstruction from memory. No inference from past conversations. No curling the live site. The orchestrator must resolve missing files as a P0 blocker.

4. **No prohibited practices.** No placeholders or TODOs in delivered code. No speculative require paths. No silent overwrites of existing files. No nested deployment artifacts. No adding required methods to interfaces without updating all implementations. No unbounded queries.

5. **Whitepaper-to-system mapping is required before implementation.** Before implementing any governance, intelligence, or consensus feature, the implementer must verify alignment with the architecture documents in `/docs/architecture/`. If no mapping exists for the planned work, the mapping must be created first.

### Stop-Work Conditions

The executor must halt and request resolution before proceeding if any of the following occur:

- Required source files are not attached to the execution prompt
- The execution prompt does not include an Enterprise Delivery Contract
- The task requires modifying a governance mechanism that has no architecture mapping
- A system invariant violation is detected during implementation
- The executor identifies a design choice that would force a known future rebuild

---

## Architecture Documents

| Document | Location | Purpose |
|----------|----------|---------|
| Enterprise Delivery Governance Whitepaper | /docs/whitepapers/enterprise-delivery-governance-v4.md | Authoritative framework definition |
| Whitepaper Index | /docs/whitepapers/INDEX.md | Document catalog with system mapping status |
| Governance Mapping | /docs/architecture/governance-mapping.md | System design mapping: DB tables, API endpoints, enforcement points |
| Task Governance v2 | /docs/architecture/task-governance-v2.md | Task schema, lifecycle, closure requirements, consensus triggers |
| Model Registry Spec | /docs/architecture/model-registry-spec.md | Model capability schema, selection logic, example entries |

---

## Orchestration Protocol

- GPT: orchestrator (planning, scoping, state management, strategic decisions)
- Claude: executor (implementation, produces deliverables and handbacks)
- Steven: operator (deployment, browser testing, final authority on strategic questions)
- Full protocol: `.contextkeeper/ORCHESTRATION-PROTOCOL.md`

### Protocol Extensions (G0)

- Execution prompts now require Enterprise Delivery Contract section
- Handbacks now require Quality Gate Results and Delivery Contract Compliance sections
- Orchestrator review now includes governance checklist (see whitepaper Section 8.3)
- Tasks carry extended governance metadata (see task-governance-v2.md)

---

## Repository Structure

```
contextkeeper-site/
  .contextkeeper/
    ORCHESTRATION-PROTOCOL.md
  app/
    api/v1/           # API endpoints (routed through index.php)
    auth/             # Authentication pages
    connectors/       # 21 connector class files + ConnectorInterface.php
    config.php        # Environment configuration
    dashboard/        # Dashboard pages
    lib/              # Core libraries (Auth, Database, Validator, Encryption, etc.)
    schema.sql        # Base database schema
    storage/          # File storage (bundles)
    templates/        # HTML templates
    vendor/           # Stripe SDK
  docs/
    whitepapers/      # Enterprise white papers (authoritative references)
      INDEX.md
      enterprise-delivery-governance-v4.md
    architecture/     # System design mappings (pre-implementation specs)
      governance-mapping.md
      task-governance-v2.md
      model-registry-spec.md
  assets/             # Static assets
  images/             # Image assets
```

---

## What a New Session Must Do

1. Read this HANDOFF.md
2. Read ORCHESTRATION-PROTOCOL.md
3. Acknowledge current system state
4. Confirm governance-first development rules are understood
5. Wait for a GPT execution prompt (with Enterprise Delivery Contract)
6. Do not infer missing context from memory
7. Do not plan future work unless explicitly asked

## G1.6 — Invariant Repair Patch (COMPLETE)

- Removed reverse linkage write from contracts.php:
  - Eliminated UPDATE governed_tasks SET delivery_contract_id
- Enforced architectural invariant:
  - delivery_contracts.task_id is the sole task-contract relationship
- Restored normalization and prevented dual-source inconsistency
- Patch applied at code level and verified (no remaining references)



---

## Control-Plane Handoff Artifacts

The following files govern executor initialization discipline.
They live in .contextkeeper/ and must be included in every fresh
executor session bundle.

| File | Purpose |
|------|---------|
| INITIALIZATION-PROMPT.md | Prompt Steven pastes to open a new executor session |
| ACCEPTANCE-CHECKLIST.md | Checklist GPT runs against every initialization response |
| INIT-RESPONSE-SCHEMA.md | Schema Claude must follow in initialization responses |
| REJECTION-RULES.md | Automatic rejection conditions GPT enforces |

These artifacts were introduced to prevent unsupported runtime claims
from passing undetected in fresh-chat handoff.

