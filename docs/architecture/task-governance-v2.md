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
