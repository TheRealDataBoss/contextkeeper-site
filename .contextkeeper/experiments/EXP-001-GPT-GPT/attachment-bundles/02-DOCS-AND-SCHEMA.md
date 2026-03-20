================================================================================
FILE: HANDOFF.md
PATH: C:\Users\Steven\contextkeeper-site\HANDOFF.md
================================================================================
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


================================================================================
FILE: INDEX.md
PATH: C:\Users\Steven\contextkeeper-site\docs\whitepapers\INDEX.md
================================================================================
# Whitepaper Index

> ContextKeeper Enterprise Engineering White Paper Series
> Maintained by: Engineering Leadership
> Last updated: 2026-03-17

## Documents

| # | Document | Version | Date | Description | System Mapping Status |
|---|----------|---------|------|-------------|-----------------------|
| 1 | [Enterprise Delivery Governance](enterprise-delivery-governance-v4.md) | 1.0 | 2026-03-17 | Comprehensive governance framework for AI-orchestrated software engineering. Defines delivery contracts, quality gates, source truth guarantees, task governance schema, model intelligence layer, multi-model consensus protocol, and extended assurance layers (formal verification, drift monitoring, outcome reinforcement, risk quantification, execution trace graphs, reproducibility contracts). | NOT IMPLEMENTED |

## Version History

| Date | Document | Change | Author |
|------|----------|--------|--------|
| 2026-03-17 | Enterprise Delivery Governance | v1.0 initial publication. 12 sections covering 12 governance mechanisms. | Engineering |

## System Mapping Status Definitions

- **NOT IMPLEMENTED**: Document exists as architectural reference only. No corresponding system code, database tables, or API endpoints exist.
- **PARTIAL**: Some mechanisms from the document have been implemented in the system. The governance-mapping.md file tracks which specific mechanisms are live.
- **IMPLEMENTED**: All mechanisms defined in the document have corresponding system implementations that are deployed, verified, and operational.

## Usage

These documents serve as authoritative architectural references. Before implementing any governance, intelligence, or consensus feature, consult the relevant whitepaper to ensure the implementation aligns with the defined architecture. Before modifying any whitepaper, ensure the change is reviewed by engineering leadership and that all downstream system mappings are updated.


================================================================================
FILE: governance-mapping.md
PATH: C:\Users\Steven\contextkeeper-site\docs\architecture\governance-mapping.md
================================================================================
# Governance Mapping

> System design mapping from Enterprise Delivery Governance Whitepaper v1.0
> This document translates whitepaper concepts into concrete system requirements.
> It is NOT implementation code. It is structured architecture specification.
>
> Status: Pre-implementation reference
> Last updated: 2026-03-17

---

## 1. Enterprise Delivery Contract

**Concept**: A mandatory section in every execution prompt specifying system invariants, security requirements, failure modes, observability, performance constraints, rollback plans, and rejection criteria. Enforced at the prompt construction boundary.

**Required DB Tables**:
- `delivery_contracts` (id, task_id, system_invariants JSON, operational_assumptions JSON, security_requirements JSON, failure_mode_requirements JSON, observability_requirements JSON, performance_constraints JSON, idempotency_requirements JSON, migration_rollback_plan TEXT, verification_evidence_required JSON, review_failure_criteria JSON, created_at, updated_at)

**Required API Endpoints**:
- `POST /api/v1/governance/contracts` - Create delivery contract for a task
- `GET /api/v1/governance/contracts/:task_id` - Retrieve contract for a task
- `PUT /api/v1/governance/contracts/:id` - Update contract (versioned, append-only history)

**Required Background Jobs**: None. Contracts are created synchronously with task creation.

**Enforcement Points**:
- Task creation: contract must be attached before executor receives the prompt
- Handback review: each contract field must have a corresponding compliance response

**Dependencies**: Task governance schema (mechanism 4)

---

## 2. Quality Gate Protocol

**Concept**: Four sequential verification gates (Build, Proof, Operations, Architecture) that every handback must pass before acceptance. Gates are evaluated in order; failure at any gate halts evaluation.

**Required DB Tables**:
- `quality_gate_evaluations` (id, task_id, handback_id, gate_name ENUM('build','proof','operations','architecture'), status ENUM('pass','fail','skip'), evidence TEXT, failure_reason TEXT, evaluated_by VARCHAR(100), evaluated_at DATETIME)

**Required API Endpoints**:
- `POST /api/v1/governance/gates/:task_id/evaluate` - Submit gate evaluation for a handback
- `GET /api/v1/governance/gates/:task_id` - Get all gate results for a task
- `GET /api/v1/governance/gates/:task_id/status` - Get aggregate pass/fail status

**Required Background Jobs**: None. Gate evaluation is synchronous with handback review.

**Enforcement Points**:
- Handback acceptance: all four gates must be evaluated before a handback can be accepted
- Task closure: task cannot transition to COMPLETE without all gates passing

**Dependencies**: Delivery contract (mechanism 1) for verification criteria

---

## 3. Source Truth Guarantee

**Concept**: A hard stop-work condition preventing speculative implementation when the executor lacks current production source files. Six rules: Attachment, Integration, No Reconstruction, No Curl, Stop-Work, P0 Resolution.

**Required DB Tables**:
- `source_attachments` (id, task_id, file_path VARCHAR(500), content_hash VARCHAR(64), attached_at DATETIME, attached_by VARCHAR(100))

**Required API Endpoints**:
- `POST /api/v1/governance/source-truth/:task_id/attach` - Record source file attachment
- `GET /api/v1/governance/source-truth/:task_id/manifest` - Get all attached source files for a task
- `POST /api/v1/governance/source-truth/:task_id/verify` - Verify that all required files were attached (compare against task specification)

**Required Background Jobs**: None.

**Enforcement Points**:
- Task assignment: executor receives source manifest; if files are missing, executor halts
- Handback review: verify that no speculative requires exist (compare delivered require paths against source manifest)

**Dependencies**: None (foundational mechanism)

---

## 4. Task Governance Schema

**Concept**: Extended task metadata carrying quality tier, enterprise criticality, model assignment, dependency tracking, consensus requirements, and closure conditions.

**Required DB Tables**:
- Extend existing `projects` or create `governed_tasks` (id, project_id, task_class ENUM('core','child','blocker','cleanup','offshoot','research','architecture','governance'), parent_task_id, origin_session_id, discovery_reason TEXT, must_complete_before_parent_close BOOLEAN, enterprise_criticality ENUM('low','medium','high','platform-critical'), quality_tier ENUM('prototype','production','enterprise','regulated-enterprise'), primary_model_recommendation VARCHAR(100), auditor_model_recommendation VARCHAR(100), consensus_required BOOLEAN, acceptance_evidence_required TEXT, rollback_required BOOLEAN, observability_required BOOLEAN, security_review_required BOOLEAN, future_rebuild_risk ENUM('none','low','medium','high'), technical_debt_if_deferred TEXT, status ENUM('open','in_progress','review','blocked','complete','rejected'), created_at, updated_at)

**Required API Endpoints**:
- `POST /api/v1/governance/tasks` - Create governed task
- `GET /api/v1/governance/tasks/:id` - Get task with full governance metadata
- `PUT /api/v1/governance/tasks/:id` - Update task
- `GET /api/v1/governance/tasks?project_id=X` - List tasks for project with governance fields
- `POST /api/v1/governance/tasks/:id/close` - Attempt task closure (validates gate status, child task status, consensus status)

**Required Background Jobs**: None.

**Enforcement Points**:
- Task creation: all governance fields must be populated (defaults applied per quality tier)
- Task closure: must_complete_before_parent_close children must be complete; all gates must pass; consensus must be resolved if required

**Dependencies**: Quality gates (mechanism 2), consensus protocol (mechanism 6) for closure validation

---

## 5. Model Intelligence Layer

**Concept**: Model-aware execution system matching task requirements to model capabilities. Includes capability registry, complexity estimator, task-to-model recommender, model switch advisor, and benchmark framework.

**Required DB Tables**:
- `model_registry` (id, provider VARCHAR(100), model_id VARCHAR(100), model_version VARCHAR(50), context_window_tokens INT, max_output_tokens INT, input_price_per_mtok FLOAT, output_price_per_mtok FLOAT, tool_use_support BOOLEAN, reasoning_depth ENUM('low','medium','high','elite'), code_generation_quality ENUM('low','medium','high','elite'), instruction_following ENUM('low','medium','high','elite'), latency_class ENUM('fast','standard','slow'), recommended_task_types JSON, known_weaknesses JSON, deprecation_status ENUM('active','deprecated','sunset'), last_evaluated DATETIME, created_at, updated_at)
- `model_recommendations` (id, task_id, best_model_id, best_value_model_id, fastest_model_id, auditor_model_id, confidence_score FLOAT, rationale TEXT, created_at)

**Required API Endpoints**:
- `GET /api/v1/models` - List all registered models
- `GET /api/v1/models/:id` - Get model details
- `POST /api/v1/models` - Register or update a model
- `POST /api/v1/models/recommend` - Get model recommendation for a task profile
- `GET /api/v1/models/compare?ids=X,Y` - Compare models side by side

**Required Background Jobs**:
- Model evaluation: quarterly re-evaluation of registered models against benchmark tasks
- Price sync: periodic check for pricing changes from providers

**Enforcement Points**:
- Task creation: model recommendation generated and stored
- Execution assignment: recommended model used unless operator overrides

**Dependencies**: Task governance schema (mechanism 4) for task profile input

---

## 6. Multi-Model Consensus Protocol

**Concept**: Multiple models independently analyze the same artifact, produce structured positions, and iterate toward convergence or escalation. Includes disagreement taxonomy, consensus states, and resolution rules.

**Required DB Tables**:
- `consensus_decisions` (id, task_id, status ENUM('unreviewed','under_comparison','partial_agreement','consensus_reached','disagreement_escalated','user_resolved','evidence_pending'), agreement_score FLOAT, conflict_count INT, conflict_types JSON, resolved_by ENUM('reconciler','evidence','operator','default_to_conservative'), resolution_method TEXT, evidence_links JSON, final_decision TEXT, residual_risk TEXT, iteration_count INT, created_at, resolved_at)
- `consensus_positions` (id, decision_id, model_id VARCHAR(100), role ENUM('proposer','reconciler','auditor'), conclusion TEXT, assumptions JSON, evidence_basis TEXT, risks TEXT, confidence ENUM('low','medium','high'), objections_to_alternatives TEXT, what_would_change_mind TEXT, created_at)

**Required API Endpoints**:
- `POST /api/v1/governance/consensus/:task_id/initiate` - Start consensus process
- `POST /api/v1/governance/consensus/:decision_id/position` - Submit a model position
- `POST /api/v1/governance/consensus/:decision_id/reconcile` - Submit reconciliation
- `GET /api/v1/governance/consensus/:task_id` - Get consensus status and positions
- `POST /api/v1/governance/consensus/:decision_id/resolve` - Operator resolution

**Required Background Jobs**: None. Consensus is driven by the orchestration cycle.

**Enforcement Points**:
- Tasks with consensus_required = true: cannot proceed to execution without consensus
- Tasks with enterprise_criticality >= high: consensus auto-triggered
- Task closure: consensus must be in terminal state (consensus_reached or user_resolved)

**Dependencies**: Model registry (mechanism 5) for model identification, task schema (mechanism 4) for trigger conditions

---

## 7. Formal Invariant Enforcement Layer

**Concept**: Invariants expressed as executable checks, automatically validated pre-deploy or post-deploy. Transitions from claim-based to proof-based assurance.

**Required DB Tables**:
- `formal_invariants` (id, project_id, name VARCHAR(255), invariant_class ENUM('database','api_contract','state_transition','idempotency','data_integrity','security_boundary'), assertion_logic TEXT, verification_method TEXT, verification_query TEXT, is_automated BOOLEAN, last_verified DATETIME, last_status ENUM('passed','failed','not_run'), created_at)
- `invariant_verification_log` (id, invariant_id, status ENUM('passed','failed'), execution_time_ms INT, failure_details TEXT, verified_at DATETIME)

**Required API Endpoints**:
- `POST /api/v1/governance/invariants` - Define a formal invariant
- `GET /api/v1/governance/invariants?project_id=X` - List invariants for project
- `POST /api/v1/governance/invariants/:id/verify` - Run verification check
- `POST /api/v1/governance/invariants/verify-all?project_id=X` - Run all checks for project
- `GET /api/v1/governance/invariants/:id/history` - Verification history

**Required Background Jobs**:
- Post-deploy verification: after each deployment, run all automated invariant checks
- Periodic verification: scheduled runs (e.g., hourly) of all automated invariants

**Enforcement Points**:
- Post-deploy: automated invariant check suite runs; failures trigger alerts
- Pre-merge: invariant checks validate that new code does not violate existing assertions

**Dependencies**: None (can be implemented independently)

---

## 8. System Drift Monitoring

**Concept**: Detection of gradual, undetected divergence between actual and intended system behavior across six drift types: code, behavioral, model, schema, cost, scoring.

**Required DB Tables**:
- `drift_baselines` (id, project_id, drift_type ENUM('code','behavioral','model','schema','cost','scoring'), baseline_snapshot JSON, captured_at DATETIME)
- `drift_signals` (id, project_id, drift_type, delta_value FLOAT, threshold FLOAT, severity ENUM('normal','warning','critical'), details JSON, detected_at DATETIME)

**Required API Endpoints**:
- `POST /api/v1/governance/drift/baseline` - Capture baseline snapshot
- `GET /api/v1/governance/drift/:project_id` - Get current drift status
- `GET /api/v1/governance/drift/:project_id/signals` - Get drift signal history
- `POST /api/v1/governance/drift/:project_id/check` - Run drift check against baselines

**Required Background Jobs**:
- Periodic drift check: scheduled comparison of current state against baselines
- Baseline rotation: automatic baseline update after verified deployments

**Enforcement Points**:
- Deployment: drift check runs automatically post-deploy
- Monitoring: periodic background checks with alerting on threshold breaches

**Dependencies**: Formal invariants (mechanism 7) for behavioral baselines

---

## 9. Outcome-Based Reinforcement Layer

**Concept**: Tracks downstream consequences of model decisions and feeds outcomes back into governance parameters. Transforms static governance into adaptive intelligence.

**Required DB Tables**:
- `task_outcomes` (id, task_id, model_id VARCHAR(100), first_attempt_gate_pass BOOLEAN, correction_cycles INT, deployment_success BOOLEAN, rollback_occurred BOOLEAN, runtime_errors_within_window INT, architecture_modification_within_period BOOLEAN, outcome_recorded_at DATETIME)
- `model_performance_scores` (id, model_id VARCHAR(100), task_type VARCHAR(100), success_rate FLOAT, avg_correction_cycles FLOAT, sample_count INT, last_updated DATETIME)

**Required API Endpoints**:
- `POST /api/v1/governance/outcomes/:task_id` - Record task outcome
- `GET /api/v1/governance/outcomes/model/:model_id` - Get model performance summary
- `GET /api/v1/governance/outcomes/analysis?task_type=X` - Get outcome analysis by task type

**Required Background Jobs**:
- Performance aggregation: periodic recalculation of model performance scores from outcome history
- Registry update: push updated scores to model registry recommendation weights

**Enforcement Points**:
- Post-observation-window: outcomes automatically recorded after deployment monitoring period
- Model selection: recommendation engine factors in historical performance

**Dependencies**: Model registry (mechanism 5), task schema (mechanism 4), quality gates (mechanism 2)

---

## 10. Quantified Risk Model

**Concept**: Replaces qualitative risk classification with quantified Expected Risk = P(failure) x Cost(failure). Drives automated escalation decisions.

**Required DB Tables**:
- `risk_assessments` (id, task_id, probability_of_failure FLOAT, impact_score FLOAT, expected_cost_of_failure FLOAT, expected_risk FLOAT, risk_threshold_action ENUM('standard','audit','consensus','human_approval'), assessment_basis TEXT, assessed_at DATETIME)

**Required API Endpoints**:
- `POST /api/v1/governance/risk/:task_id/assess` - Calculate risk for a task
- `GET /api/v1/governance/risk/:task_id` - Get risk assessment
- `GET /api/v1/governance/risk/thresholds` - Get current threshold configuration

**Required Background Jobs**:
- Risk model calibration: periodic recalibration of P(failure) estimates from outcome data

**Enforcement Points**:
- Task creation: risk assessment generated; determines whether consensus is auto-triggered
- Execution: risk threshold determines required approval level

**Dependencies**: Outcome reinforcement (mechanism 9) for P(failure) estimates, task schema (mechanism 4)

---

## 11. Execution Trace Graph

**Concept**: Connected graph of tasks, decisions, model outputs, consensus events, deployments, outcomes, and drift signals. Enables audit replay, root cause tracing, compliance reporting, and pattern analysis.

**Required DB Tables**:
- `trace_nodes` (id, node_type ENUM('task','decision','model_output','consensus_event','deployment','outcome','drift_signal'), reference_id VARCHAR(36), metadata JSON, created_at DATETIME)
- `trace_edges` (id, source_node_id, target_node_id, edge_type ENUM('depends_on','parent_of','produced_by','satisfies','deploys','measures','feeds_back','detected_in','caused_by'), metadata JSON, created_at DATETIME)

**Required API Endpoints**:
- `POST /api/v1/governance/trace/node` - Create trace node
- `POST /api/v1/governance/trace/edge` - Create trace edge
- `GET /api/v1/governance/trace/:node_id/subgraph` - Get connected subgraph from a node
- `GET /api/v1/governance/trace/audit/:task_id` - Full audit trail for a task (traverse backward)
- `GET /api/v1/governance/trace/root-cause/:outcome_id` - Root cause analysis from an outcome

**Required Background Jobs**:
- Graph maintenance: automatic node/edge creation as tasks, deployments, and outcomes occur
- Pattern analysis: periodic analysis of graph patterns (high correction rate paths, model pair effectiveness)

**Enforcement Points**:
- All governance operations: automatically create trace nodes and edges
- Compliance audit: trace graph provides complete, machine-readable audit trail

**Dependencies**: All other mechanisms (trace graph connects them all)

---

## 12. Reproducibility Contract

**Concept**: Every task execution records sufficient information to reproduce the result exactly. Includes input manifest, model identification, prompt version, environment config, execution parameters, output manifest, and timestamp.

**Required DB Tables**:
- `reproducibility_records` (id, task_id, input_manifest JSON, model_id VARCHAR(100), model_version VARCHAR(50), prompt_hash VARCHAR(64), environment_config JSON, execution_parameters JSON, output_manifest JSON, output_hash VARCHAR(64), execution_start DATETIME, execution_end DATETIME, reproduction_verified BOOLEAN, verified_at DATETIME)

**Required API Endpoints**:
- `POST /api/v1/governance/reproducibility/:task_id` - Record reproducibility state
- `GET /api/v1/governance/reproducibility/:task_id` - Get reproducibility record
- `POST /api/v1/governance/reproducibility/:task_id/verify` - Attempt reproduction and compare

**Required Background Jobs**:
- Reproduction verification: for regulated-enterprise tasks, automated reproduction attempt after initial execution

**Enforcement Points**:
- Task completion: reproducibility record must be captured before task can close
- Regulated tasks: reproduction verification is part of acceptance criteria

**Dependencies**: Model registry (mechanism 5) for model version tracking

---

## Implementation Priority

| Priority | Mechanism | Rationale |
|----------|-----------|-----------|
| P0 | Task Governance Schema | Foundation for all other mechanisms; extends existing task model |
| P0 | Enterprise Delivery Contract | Directly prevents the most observed failure modes |
| P0 | Source Truth Guarantee | Highest single-impact process control |
| P0 | Quality Gate Protocol | Enforcement layer for delivery contract compliance |
| P1 | Model Intelligence Layer | Enables informed model selection; foundation for consensus |
| P1 | Multi-Model Consensus Protocol | Addresses single-model blind spots on critical decisions |
| P1 | Formal Invariant Enforcement | Transitions from claim-based to proof-based assurance |
| P2 | Execution Trace Graph | Connects all mechanisms for audit and analysis |
| P2 | Reproducibility Contract | Required for regulated-enterprise compliance |
| P2 | Outcome-Based Reinforcement | Transforms static governance to adaptive intelligence |
| P2 | System Drift Monitoring | Detects silent degradation over time |
| P3 | Quantified Risk Model | Requires outcome data to calibrate; implement after reinforcement layer |


================================================================================
FILE: task-governance-v2.md
PATH: C:\Users\Steven\contextkeeper-site\docs\architecture\task-governance-v2.md
================================================================================
# Task Governance v2

> Process specification for governed task lifecycle
> Derived from: Enterprise Delivery Governance Whitepaper v1.0, Section 3.4
> Status: Pre-implementation reference
> Last updated: 2026-03-17

---

## 1. Task Schema

Every task in the system carries the following fields. Fields marked REQUIRED must be populated at task creation. Fields marked COMPUTED are derived by the system.

### 1.1 Identity Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | string (UUID) | COMPUTED | Unique task identifier |
| project_id | string | REQUIRED | Parent project |
| title | string | REQUIRED | Human-readable task title |
| description | text | REQUIRED | Full task specification |
| created_at | datetime | COMPUTED | Creation timestamp |
| updated_at | datetime | COMPUTED | Last modification timestamp |

### 1.2 Classification Fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| task_class | enum | REQUIRED | core | One of: core, child, blocker, cleanup, offshoot, research, architecture, governance |
| parent_task_id | string or null | OPTIONAL | null | Reference to parent task for hierarchical decomposition |
| origin_session_id | string or null | OPTIONAL | null | Session where this task was discovered (audit trail) |
| discovery_reason | text | REQUIRED | - | Why this task was created; prevents orphaned tasks |
| must_complete_before_parent_close | boolean | REQUIRED | false | If true, parent task cannot close while this task is open |

### 1.3 Quality Governance Fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| enterprise_criticality | enum | REQUIRED | medium | One of: low, medium, high, platform-critical |
| quality_tier | enum | REQUIRED | enterprise | One of: prototype, production, enterprise, regulated-enterprise |
| primary_model_recommendation | string | COMPUTED | - | Recommended execution model from model intelligence layer |
| auditor_model_recommendation | string | COMPUTED | - | Recommended audit model |
| consensus_required | boolean | COMPUTED | - | Whether multi-model consensus is needed (derived from criticality and risk) |
| acceptance_evidence_required | text | REQUIRED | - | What must be demonstrated before this task can close |
| rollback_required | boolean | REQUIRED | true | Whether the deliverable must include a rollback mechanism |
| observability_required | boolean | REQUIRED | true | Whether logging and monitoring are required |
| security_review_required | boolean | REQUIRED | false | Whether explicit security analysis is required |
| future_rebuild_risk | enum | REQUIRED | - | One of: none, low, medium, high. Must be assessed, not defaulted. |
| technical_debt_if_deferred | text | OPTIONAL | null | Makes the cost of deferral explicit |

### 1.4 Lifecycle Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| status | enum | COMPUTED | One of: open, in_progress, review, blocked, complete, rejected |
| assigned_model | string | OPTIONAL | Actual model used for execution |
| delivery_contract_id | string or null | OPTIONAL | Reference to attached delivery contract |
| consensus_decision_id | string or null | OPTIONAL | Reference to consensus decision if applicable |
| risk_assessment_id | string or null | OPTIONAL | Reference to quantified risk assessment |
| reproducibility_record_id | string or null | OPTIONAL | Reference to reproducibility state capture |

---

## 2. Task Spawn Logic

When a new task is discovered during execution, it must be classified immediately using this decision tree. No task may be added without classification.

### 2.1 Decision Tree

**Step 1: Does it block correctness, safety, or enterprise-grade architecture?**
- YES: Create with task_class = blocker, enterprise_criticality >= high. Promote immediately in priority. If it blocks the current task, set must_complete_before_parent_close = true on the current task's parent relationship.
- NO: Proceed to Step 2.

**Step 2: Does it prevent the parent task from being truly final-grade?**
- YES: Create with task_class = child, must_complete_before_parent_close = true. Parent task cannot close until this child is complete.
- NO: Proceed to Step 3.

**Step 3: Is it valuable but not required for current task closure?**
- YES: Create with task_class = offshoot. Populate all classification and governance fields. Do not block parent closure.
- NO: Proceed to Step 4.

**Step 4: Is it about governance, observability, model choice, or scale-readiness?**
- YES: Create with enterprise_criticality >= high regardless of apparent scope. Enterprise quality work is never classified as low-priority polish.
- NO: Evaluate whether the task is genuinely needed. If uncertain, create as offshoot with documentation.

### 2.2 Spawn Requirements

Every spawned task must include:
- discovery_reason explaining why it was created
- parent_task_id linking to the task during which it was discovered
- origin_session_id recording the session context
- All quality governance fields populated (no empty fields; use explicit "not applicable" with rationale)

---

## 3. Task Lifecycle Rules

### 3.1 Status Transitions

```
open -> in_progress    (assigned to executor)
in_progress -> review  (handback submitted)
review -> complete     (all closure requirements met)
review -> rejected     (gate failure or contract non-compliance)
rejected -> in_progress (correction cycle begins)
any -> blocked         (dependency or stop-work condition)
blocked -> in_progress (blocker resolved)
```

### 3.2 Closure Requirements

A task may transition to COMPLETE only when ALL of the following are true:

1. All four quality gates (Build, Proof, Operations, Architecture) have status = pass
2. All delivery contract fields have corresponding compliance responses in the handback
3. If must_complete_before_parent_close children exist: all are in COMPLETE status
4. If consensus_required = true: consensus decision is in terminal state (consensus_reached or user_resolved)
5. If quality_tier = regulated-enterprise: reproducibility record exists and reproduction has been verified
6. If rollback_required = true: rollback procedure is documented and tested
7. No prohibited practices detected (placeholders, speculative requires, silent overwrites, unbounded queries)

### 3.3 Rejection Protocol

When a handback fails a quality gate:
1. The specific gate failure is documented with the failure reason
2. The task transitions to REJECTED
3. The executor receives the failure details and the delivery contract for the correction attempt
4. The correction attempt follows the same lifecycle (review, gate evaluation, closure requirements)
5. Correction cycle count is incremented on the task record

---

## 4. Consensus Triggers

Multi-model consensus is automatically required when:

| Condition | Trigger |
|-----------|---------|
| quality_tier = regulated-enterprise | Always |
| enterprise_criticality = platform-critical | Always |
| enterprise_criticality = high | Default (overridable by operator) |
| consensus_required = true (manually set) | Always |
| Expected Risk > high threshold | Always (from quantified risk model) |

Consensus is NOT required when:
- enterprise_criticality = low or medium AND quality_tier = prototype or production
- Task is a cleanup or offshoot with no architectural impact
- Operator explicitly waives consensus with documented rationale

---

## 5. Risk-Triggered Escalation

When the quantified risk model (once implemented) produces an Expected Risk value, the following escalation rules apply:

| Risk Level | Action |
|------------|--------|
| Expected Risk < 0.2 | Standard execution: single model, standard gates |
| 0.2 <= Expected Risk < 0.5 | Enhanced review: single model execution with post-hoc cross-model audit |
| 0.5 <= Expected Risk < 0.8 | Full consensus: multi-model consensus required before execution |
| Expected Risk >= 0.8 | Human approval: operator must approve execution plan regardless of consensus outcome |

Until the risk model is calibrated with outcome data, these thresholds are applied qualitatively based on enterprise_criticality and quality_tier.

---

## 6. Default Values

For the ContextKeeper platform, the following defaults apply unless explicitly overridden:

| Field | Default | Override Condition |
|-------|---------|-------------------|
| quality_tier | enterprise | Operator may set to production for non-critical utilities |
| enterprise_criticality | high | For governance, billing, auth, audit, task, model-routing, and agent execution subsystems |
| enterprise_criticality | platform-critical | For any subsystem where failure corrupts user data or breaches security |
| rollback_required | true | For any task modifying database schema or overwriting existing files |
| observability_required | true | For any task creating or modifying API endpoints |
| security_review_required | true | For any task touching authentication, authorization, encryption, or user data |
| future_rebuild_risk | (must be assessed) | No default. Assessment is mandatory for every core and architecture task. |


================================================================================
FILE: model-registry-spec.md
PATH: C:\Users\Steven\contextkeeper-site\docs\architecture\model-registry-spec.md
================================================================================
# Model Registry Specification

> Schema definition and selection logic for the Model Intelligence Layer
> Derived from: Enterprise Delivery Governance Whitepaper v1.0, Section 5
> Status: Pre-implementation reference
> Last updated: 2026-03-17

---

## 1. Registry Schema

Each model registered in the system is characterized by the following fields.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | string (UUID) | COMPUTED | Internal registry identifier |
| provider | string | REQUIRED | Model provider organization |
| model_id | string | REQUIRED | Exact API model identifier used in calls |
| model_version | string | REQUIRED | Version or date stamp |
| display_name | string | REQUIRED | Human-readable name |
| context_window_tokens | integer | REQUIRED | Maximum input token capacity |
| max_output_tokens | integer | REQUIRED | Maximum output token capacity |
| input_price_per_mtok | float | REQUIRED | USD cost per million input tokens |
| output_price_per_mtok | float | REQUIRED | USD cost per million output tokens |
| modalities | array of string | REQUIRED | Supported input types: text, image, audio, video, document |
| tool_use_support | boolean | REQUIRED | Whether function calling or tool use is supported |
| streaming_support | boolean | REQUIRED | Whether streaming responses are supported |
| reasoning_depth | enum | REQUIRED | low, medium, high, elite |
| code_generation_quality | enum | REQUIRED | low, medium, high, elite |
| instruction_following | enum | REQUIRED | low, medium, high, elite |
| creative_quality | enum | REQUIRED | low, medium, high, elite |
| latency_class | enum | REQUIRED | fast, standard, slow |
| recommended_task_types | array of string | REQUIRED | Task classes this model excels at |
| known_weaknesses | array of string | REQUIRED | Task types or patterns where this model underperforms |
| deprecation_status | enum | REQUIRED | active, deprecated, sunset |
| last_evaluated | datetime | REQUIRED | When capabilities were last benchmarked |
| evaluation_notes | text | OPTIONAL | Notes from last evaluation |

---

## 2. Example Registry Entries

### 2.1 Claude Opus 4 (Anthropic)

| Field | Value |
|-------|-------|
| provider | Anthropic |
| model_id | claude-opus-4-20250514 |
| model_version | 2025-05-14 |
| display_name | Claude Opus 4 |
| context_window_tokens | 200000 |
| max_output_tokens | 32000 |
| input_price_per_mtok | 15.00 |
| output_price_per_mtok | 75.00 |
| modalities | text, image, document |
| tool_use_support | true |
| streaming_support | true |
| reasoning_depth | elite |
| code_generation_quality | elite |
| instruction_following | elite |
| creative_quality | high |
| latency_class | slow |
| recommended_task_types | complex architecture, multi-file code generation, governed execution with delivery contracts, cross-codebase integration, formal analysis |
| known_weaknesses | cost-prohibitive for high-volume simple tasks, slower response times |
| deprecation_status | active |
| last_evaluated | 2026-03-17 |

### 2.2 Claude Sonnet 4 (Anthropic)

| Field | Value |
|-------|-------|
| provider | Anthropic |
| model_id | claude-sonnet-4-20250514 |
| model_version | 2025-05-14 |
| display_name | Claude Sonnet 4 |
| context_window_tokens | 200000 |
| max_output_tokens | 16000 |
| input_price_per_mtok | 3.00 |
| output_price_per_mtok | 15.00 |
| modalities | text, image, document |
| tool_use_support | true |
| streaming_support | true |
| reasoning_depth | high |
| code_generation_quality | high |
| instruction_following | high |
| creative_quality | high |
| latency_class | fast |
| recommended_task_types | standard code generation, documentation, testing, code review, moderate-complexity tasks, high-volume operations |
| known_weaknesses | may miss subtle architectural issues on very complex multi-system tasks |
| deprecation_status | active |
| last_evaluated | 2026-03-17 |

### 2.3 GPT-4o (OpenAI)

| Field | Value |
|-------|-------|
| provider | OpenAI |
| model_id | gpt-4o |
| model_version | 2025-03 |
| display_name | GPT-4o |
| context_window_tokens | 128000 |
| max_output_tokens | 16384 |
| input_price_per_mtok | 2.50 |
| output_price_per_mtok | 10.00 |
| modalities | text, image, audio |
| tool_use_support | true |
| streaming_support | true |
| reasoning_depth | high |
| code_generation_quality | high |
| instruction_following | high |
| creative_quality | high |
| latency_class | fast |
| recommended_task_types | planning, orchestration, task decomposition, creative content, multi-modal analysis, rapid prototyping |
| known_weaknesses | may produce overconfident architectural claims without verification, less precise on strict protocol adherence |
| deprecation_status | active |
| last_evaluated | 2026-03-17 |

### 2.4 o3 (OpenAI)

| Field | Value |
|-------|-------|
| provider | OpenAI |
| model_id | o3 |
| model_version | 2025-01 |
| display_name | o3 Reasoning |
| context_window_tokens | 200000 |
| max_output_tokens | 100000 |
| input_price_per_mtok | 10.00 |
| output_price_per_mtok | 40.00 |
| modalities | text, image |
| tool_use_support | true |
| streaming_support | true |
| reasoning_depth | elite |
| code_generation_quality | high |
| instruction_following | high |
| creative_quality | medium |
| latency_class | slow |
| recommended_task_types | complex reasoning chains, mathematical proofs, formal verification, multi-step debugging, root cause analysis |
| known_weaknesses | high cost, slow latency, less effective for creative or communication tasks |
| deprecation_status | active |
| last_evaluated | 2026-03-17 |

---

## 3. Selection Logic

### 3.1 Input Signals

The model recommender evaluates these signals from the task profile:

| Signal | Source | Impact |
|--------|--------|--------|
| Task type | task_class field | Matches against recommended_task_types |
| Quality tier | quality_tier field | Higher tiers require higher capability models |
| Enterprise criticality | enterprise_criticality field | Platform-critical tasks require elite reasoning |
| Context requirements | Estimated from attached file count and sizes | Must fit within context_window_tokens |
| Budget constraints | Project or task budget | Filters by price_per_mtok |
| Consensus role | Whether model is being assigned as proposer, auditor, or reconciler | Auditor should be a different model from proposer |
| Security sensitivity | security_review_required field | Sensitive tasks require elite instruction_following |

### 3.2 Recommendation Output

For each task, the recommender produces:

| Recommendation | Selection Criteria |
|----------------|--------------------|
| Best Model | Highest capability match regardless of cost |
| Best Value Model | Lowest cost model that meets minimum quality tier requirements |
| Fastest Model | Lowest latency class among qualifying models |
| Recommended Auditor | Different provider from Best Model; high instruction_following; good at finding defects |
| Confidence Score | Float [0,1] based on how well the task profile matches model capabilities and how recently models were evaluated |

### 3.3 Selection Rules

1. A model cannot be recommended if its context_window_tokens is smaller than the estimated context requirement.
2. A model cannot be recommended for a task with quality_tier = enterprise or higher if its code_generation_quality or instruction_following is below "high".
3. A model cannot be recommended as auditor if it is the same model_id as the proposer.
4. A model with deprecation_status = deprecated may be recommended only with a warning. A model with deprecation_status = sunset is excluded entirely.
5. If no model meets all requirements, the recommender returns the best partial match with an explicit list of unmet criteria, and sets confidence_score < 0.5 to trigger human review.

### 3.4 Override Rules

The operator may override any recommendation. Overrides are recorded in the task metadata with:
- Original recommendation
- Override decision
- Rationale for override
- Operator identity

Overrides are auditable and visible in the execution trace graph.


================================================================================
FILE: schema.sql
PATH: C:\Users\Steven\contextkeeper-site\app\schema.sql
================================================================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255),
  name VARCHAR(255),
  google_id VARCHAR(255) UNIQUE,
  plan ENUM('free','pro','team','enterprise') DEFAULT 'free',
  stripe_customer_id VARCHAR(255),
  stripe_subscription_id VARCHAR(255),
  api_key VARCHAR(64) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  state_vector JSON,
  current_state VARCHAR(50) DEFAULT 'UNINITIATED',
  sessions_count INT DEFAULT 0,
  decisions_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_project (user_id, slug)
);

CREATE TABLE IF NOT EXISTS sessions_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  agent VARCHAR(50) NOT NULL,
  action ENUM('sync','bootstrap','init','doctor','bundle') NOT NULL,
  decisions_captured INT DEFAULT 0,
  invariants_captured INT DEFAULT 0,
  files_captured INT DEFAULT 0,
  authority_sha VARCHAR(64),
  repo_sha VARCHAR(64),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS decisions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  session_id INT,
  title VARCHAR(500) NOT NULL,
  rationale TEXT,
  alternatives_rejected JSON,
  established_by VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invariants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  assertion TEXT,
  scope VARCHAR(255),
  established_by VARCHAR(100),
  active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS connectors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  config_encrypted TEXT NOT NULL,
  last_sync TIMESTAMP NULL,
  status ENUM('active','error','disconnected') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS usage_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  project_id INT,
  metadata JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


