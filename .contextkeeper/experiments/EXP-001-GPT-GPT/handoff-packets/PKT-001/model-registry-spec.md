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
