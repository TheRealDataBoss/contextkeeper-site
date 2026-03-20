# Executor Initialization Rejection Rules v1.2

GPT rejects an initialization response automatically if any of the
following conditions are true. Rejection must cite the specific rule
violated and the exact text that triggered it.

## R-01: Unlabeled Factual Claim
Any factual claim in Section 1 that does not carry [HANDOFF],
[SOURCE], or [UNKNOWN] is an automatic rejection.

## R-02: Runtime Assertion Without HANDOFF Source
Any claim asserting that a feature, endpoint, table, or behavior
is currently live, deployed, or operational in production, when
that claim is not directly stated in HANDOFF.md, is an automatic
rejection.

## R-03: Evidence Tier Collapse
Any claim that labels a [SOURCE]-only finding as [HANDOFF], or
presents a [SOURCE] finding in language that implies confirmed
production status, is an automatic rejection.

## R-04: Governance Table Production Claim
Any claim that governance DB tables (governed_tasks,
delivery_contracts, delivery_contract_history,
quality_gate_evaluations, source_attachments) exist in production
is an automatic rejection. These tables are absent from the
attached schema.sql. Their production existence is [UNKNOWN].

## R-05: INDEX.md Status Contradiction
Any response that contradicts, omits, or softens the NOT IMPLEMENTED
status recorded in INDEX.md for Enterprise Delivery Governance is
an automatic rejection.

## R-06: Extra Sections
Any response containing sections beyond the four required sections
is an automatic rejection.

## R-07: Readiness Language
Any phrase indicating readiness to receive a prompt such as "Ready.",
"Waiting for GPT execution prompt.", "Standing by.", or equivalent,
appearing outside the four required sections is an automatic rejection.

## R-08: Code, Proposals, or Simulated Execution
Any response containing code, proposed changes, or simulated
execution output is an automatic rejection.

## R-09: Missing Required Protocol Elements
Any Section 2 that omits one or more of these required elements
is an automatic rejection:
  - Source Truth Guarantee
  - Delivery Contract requirement
  - Four Quality Gates requirement
  - PowerShell-only rule
  - Post-handback stop rule

## R-10: Missing Schema Gap Flag
Any Section 4 that does not contain a GAP entry naming schema.sql
and identifying the absence of governed_tasks, delivery_contracts,
delivery_contract_history, quality_gate_evaluations, and
source_attachments from that file is an automatic rejection.

## R-11: Speculative Blocker
Any Section 4 entry that asserts a blocker not directly evidenced
by a conflict or gap traceable to a specific element in a specific
attached file is an automatic rejection. Inference, expectation,
and general best practice do not constitute evidence. Every entry
must name the specific file and the specific element within that
file that establishes the conflict or gap.

## R-12: Inference from Memory
Any claim that can only be true if Claude drew on training memory
rather than attached files is an automatic rejection. If GPT cannot
trace the claim to a specific line in an attached file, it is
treated as memory inference.

## R-13: Source-Backed Claim Overstates Visible Code
Any [SOURCE] claim in Section 1c that does not use one of the
permitted statement forms (File X is present. / Class X is defined
in file Y. / Function X is defined in file Y. / Method X is defined
in file Y. / Query type X is present in file Y. / Validation check X
is present in file Y. / Constant X is declared in file Y. / Enum X
is declared in file Y. / Allowed-value set X is declared in file Y.)
is an automatic rejection. Every claim must name the element and use
only these forms. Any claim using behavior-implying verbs without a
verbatim quote from the attached source establishing that exact
characterization is rejected under R-13.

## R-14: Interpretive Wording in Section 4
Any Section 4 entry that uses interpretive phrases such as "authored",
"implemented", "crafted", "built", "developed", or similar words to
characterize attached source files is an automatic rejection, unless
that exact phrase is a direct quote from an attached file. Section 4
must use neutral source-truth wording: "attached source files" or the
exact filename. Characterizations that assert intent, quality, or
verified completion beyond directly visible file content are rejected
under R-14.

## R-15: Unclassified Section 4 Entry
Any Section 4 entry that is not explicitly classified as either
CONFLICT or GAP is an automatic rejection. Entries using any other
classification, including unlabeled prose, are rejected under R-15
unless the entire section consists of exactly the statement
"None detected."

## R-16: Blocker Overreach
Any Section 4 entry that labels a condition stop-work when the cited
conflict or gap does not directly prevent execution of the described
task is an automatic rejection. An entry that does not block
execution must be labeled non-blocking within its CONFLICT or GAP
classification. An entry cannot be labeled stop-work solely on the
basis that information is incomplete unless that incompleteness
directly prevents a required execution step.

## R-17: Section 1c Multi-File Inference
Any [SOURCE] claim in Section 1c that combines facts from two or
more attached files inside a single statement is an automatic
rejection. Every Section 1c claim must be file-local: exactly one
named element in exactly one named file. Claims that establish
connectivity, dispatch, routing, wiring, or shared behavior by
joining information from multiple files are rejected under R-17
regardless of whether the individual per-file facts are accurate.

## R-18: Section 1c Non-File-Local Statement
Any [SOURCE] claim in Section 1c that does not name exactly one
attached file is an automatic rejection. Claims that omit the file
name, refer to "the codebase", "the source files", or any collective
reference are rejected under R-18. Claims that name one file but
depend on a second file to be valid are rejected under R-18.

## R-19: Section 1c Banned Phrasing
Any [SOURCE] claim in Section 1c that contains any of the following
phrases is an automatic rejection unless the phrase appears inside a
direct verbatim quote from the specific attached file cited, and the
quote is explicitly marked as such:

  dispatched, wired, reachable, references, linked, connected,
  routes to, exposed by, available through, included by, loaded by,
  therefore, implying, indicating, via, through (when describing
  inter-file connectivity), across.

Rejection under R-19 is triggered by the presence of any banned
phrase regardless of the surrounding sentence structure.

## R-20: Section 1c Absence-to-Behavior Inference
Any [SOURCE] claim in Section 1c that uses the absence of an element
in one file to imply behavior, capability, or system state is an
automatic rejection. Absence observations are not permitted in
Section 1c under any framing. Statements of the form "file X contains
no Y therefore Z" or "because Y is absent from X, the system does Z"
or any structural equivalent are rejected under R-20. Absence findings
must appear in Section 4 as GAP entries naming the single file where
the information is absent.

