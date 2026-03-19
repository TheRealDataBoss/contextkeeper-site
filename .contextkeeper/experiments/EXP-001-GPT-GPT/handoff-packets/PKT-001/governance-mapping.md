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
