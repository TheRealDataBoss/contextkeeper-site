# Executor Initialization Acceptance Checklist

GPT runs this checklist against every initialization response before
accepting. A single FAIL is grounds for rejection with exact reason.

## Section 1 Checks

- [ ] S1-01: Every claim in Section 1 carries exactly one of the
      labels [HANDOFF], [SOURCE], or [UNKNOWN]. No unlabeled claims.

- [ ] S1-02: No claim labeled [SOURCE] asserts or implies that a
      feature, table, or endpoint is live, deployed, or operational
      in production.

- [ ] S1-03: No claim labeled [HANDOFF] asserts more than what
      HANDOFF.md explicitly states. Paraphrase is acceptable;
      strengthening is not.

- [ ] S1-04: Governance endpoints (tasks, contracts, source, gates)
      are classified [SOURCE] for code existence and [UNKNOWN] for
      production deployment status.

- [ ] S1-05: Governance DB tables (governed_tasks, delivery_contracts,
      delivery_contract_history, quality_gate_evaluations,
      source_attachments) are classified [UNKNOWN] for production
      existence, because they are absent from the attached schema.sql.

- [ ] S1-06: INDEX.md NOT IMPLEMENTED status for Enterprise Delivery
      Governance is stated explicitly and not contradicted.

- [ ] S1-07: No runtime inference appears anywhere in Section 1.
      Phrases like "code executes against them", "live", "operational",
      or "deployed" do not appear unless directly quoted from HANDOFF.md.

- [ ] S1-08: Every [SOURCE] claim in Section 1c uses exactly one of
      the permitted statement forms: File X is present. / Class X is
      defined in file Y. / Function X is defined in file Y. / Method X
      is defined in file Y. / Query type X is present in file Y. /
      Validation check X is present in file Y. / Constant X is declared
      in file Y. / Enum X is declared in file Y. / Allowed-value set X
      is declared in file Y. No other forms are permitted.

- [ ] S1-09: No [SOURCE] claim in Section 1c uses behavior-implying
      verbs such as "handles", "enforces", "produces", "validates",
      "dispatches", "processes", or "manages" unless that exact verb
      appears verbatim in a string literal or comment in the attached
      source file being described, and the claim quotes that literal
      or comment directly.

- [ ] S1-10: No [SOURCE] claim in Section 1c references more than one
      file inside a single statement. Every claim is file-local: one
      claim, one file, one named element.

- [ ] S1-11: No [SOURCE] claim in Section 1c uses any of the following
      phrases or their variants: dispatched, wired, reachable,
      references, linked, connected, routes to, exposed by, available
      through, included by, loaded by, therefore, implying, indicating,
      via, through, across.

- [ ] S1-12: No [SOURCE] claim in Section 1c uses the absence of an
      element in one file to imply behavior, capability, or state.
      Absence findings must appear in Section 4 as GAP entries only.

- [ ] S1-13: No [SOURCE] claim in Section 1c asserts routing
      connectivity that requires reading more than one file to
      establish. Route declarations inferred from combining index.php
      with governance.php or any other file pair are prohibited.

- [ ] S1-14: No [SOURCE] claim in Section 1c combines facts from two
      or more files into a single statement to assert connectivity,
      wiring, dispatch, or shared behavior.

## Section 2 Checks

- [ ] S2-01: Protocol understanding is drawn from attached
      ORCHESTRATION-PROTOCOL.md and HANDOFF.md only.

- [ ] S2-02: Source Truth Guarantee stop-work condition is stated.

- [ ] S2-03: Delivery Contract requirement is stated.

- [ ] S2-04: Four Quality Gates requirement is stated.

- [ ] S2-05: No placeholders/TODOs rule is stated.

- [ ] S2-06: PowerShell-only terminal command rule is stated.

- [ ] S2-07: Post-handback stop rule is stated (no next-step
      suggestions after handback).

## Section 3 Checks

- [ ] S3-01: Handback format includes all required sections:
      Task, Delivered, Deployment, Verification, Issues,
      Quality Gate Results, Delivery Contract Compliance,
      GPT State Update.

- [ ] S3-02: Quality Gate Results section names all four gates:
      Build, Proof, Operations, Architecture.

## Section 4 Checks

- [ ] S4-01: Every entry in Section 4 is classified as either
      CONFLICT or GAP. No entry uses any other classification.
      If no entries exist, the section states exactly
      "None detected."

- [ ] S4-02: Every CONFLICT entry names exactly two attached files
      and cites the specific contradictory statements from each.

- [ ] S4-03: Every GAP entry names exactly one attached file and
      states the specific information absent from that file.

- [ ] S4-04: The absence of governance tables from schema.sql is
      present as a GAP entry naming schema.sql as the file and
      stating that governed_tasks, delivery_contracts,
      delivery_contract_history, quality_gate_evaluations, and
      source_attachments are absent from it.

- [ ] S4-05: Any entry involving INDEX.md NOT IMPLEMENTED status
      must be classified as CONFLICT only if two attached files make
      directly contradictory statements about implementation status.
      Mere presence of governance-related source files is not by
      itself a CONFLICT. If attached evidence is missing to reconcile
      source presence with system status, the entry must be classified
      as a GAP and must name the specific file where the reconciling
      information is absent.

- [ ] S4-06: No Section 4 entry asserts a blocker based on
      inference, expectation, or general best practice. Every
      entry is traceable to a specific element in a specific
      attached file.

- [ ] S4-07: Every entry labeled stop-work identifies a conflict or
      gap that directly prevents execution of the described task.
      Every entry that does not block execution is labeled
      non-blocking.

- [ ] S4-08: Section 4 uses neutral source-truth wording only.
      Phrases such as "authored", "implemented", "crafted",
      "built", or similar interpretive words do not appear unless
      directly quoted from an attached file.

## Overall

- [ ] OV-01: Response contains exactly four sections, no more.

- [ ] OV-02: No readiness language appears outside the four sections.

- [ ] OV-03: No code, no proposed changes, no simulated execution.

