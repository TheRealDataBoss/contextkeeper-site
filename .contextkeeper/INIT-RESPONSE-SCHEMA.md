# Executor Initialization Response Schema v1.2

This schema is mandatory for all Claude executor initialization
responses on contextkeeper.org. Deviation is grounds for rejection.

## Top-Level Structure

The response must contain exactly these four H2 sections in this order:

1. ## Confirmed Current System State
2. ## Confirmed Executor Protocol Understanding
3. ## Exact Handback Format
4. ## Inconsistencies and STOP-WORK Blockers

No other top-level sections are permitted.
No introductory paragraph before Section 1.
No concluding paragraph after Section 4.

## Section 1 Schema: Confirmed Current System State

Every factual claim must be prefixed with exactly one evidence-tier
label from this set:

  [HANDOFF] -- explicitly stated in HANDOFF.md
  [SOURCE]  -- derivable from attached source files;
               runtime/production status not confirmed
  [UNKNOWN] -- cannot be determined from attached files

### Required Subsections

#### 1a. HANDOFF-documented deployed system
List items from HANDOFF.md "Deployed and Verified" only.
All items carry [HANDOFF] label.
Do not add items not present in HANDOFF.md.

#### 1b. HANDOFF-documented production DB tables
List exactly the tables named in HANDOFF.md.
All items carry [HANDOFF] label.

#### 1c. Source-backed code findings

Every [SOURCE] claim must use exactly one of the following permitted
statement forms. No other statement forms are permitted.

  Permitted forms:
    - File X is present.
    - Class X is defined in file Y.
    - Function X is defined in file Y.
    - Method X is defined in file Y.
    - Query type X is present in file Y.
    - Validation check X is present in file Y.
    - Constant X is declared in file Y.
    - Enum X is declared in file Y.
    - Allowed-value set X is declared in file Y.

Every claim must:
  - Name the specific element explicitly.
  - Name exactly one attached file explicitly.
  - Be file-local: one claim references one file only.
  - Reference an element directly visible in that one file.

If establishing a statement requires combining facts from two or more
files, that statement is prohibited from Section 1c. If the
combination reveals a conflict, place it in Section 4 as CONFLICT.
If the combination reveals missing information, place it in Section 4
as GAP.

All items carry [SOURCE] label.

#### Banned Phrases in Section 1c

The following phrases are prohibited in Section 1c unless they appear
inside a direct verbatim quote from the specific attached file being
cited, and the quote is explicitly marked as such:

  - dispatched
  - wired
  - reachable
  - references
  - linked
  - connected
  - routes to
  - exposed by
  - available through
  - included by
  - loaded by
  - therefore
  - implying
  - indicating
  - via
  - through (when describing inter-file connectivity)
  - across

#### Additional Prohibitions in Section 1c

  - Any claim that references more than one file inside a single
    [SOURCE] statement.
  - Any claim about routing connectivity inferred from multiple files.
  - Any claim about dispatch behavior or call chains across files.
  - Any claim about runtime reachability or execution paths.
  - Any claim that uses the absence of an element in one file to
    imply behavior, capability, or system state. Such findings must
    appear in Section 4 as GAP entries naming the single file where
    the information is absent.
  - Any route declaration claim that requires reading more than one
    file to confirm the route exists.
  - Any claim using behavior-implying verbs: handles, enforces,
    produces, validates, dispatches, processes, manages, executes,
    checks, ensures, prevents, allows, rejects, accepts.

#### 1d. Unknown / not provable from attached files
List items that cannot be confirmed.
All items carry [UNKNOWN] label.
Must include at minimum:
  - Production deployment status of governance endpoints.
  - Existence of governance DB tables in production.
  - Any runtime behavior not directly stated in HANDOFF.md.

### Prohibited Phrases in Section 1 (all subsections)

The following phrases are prohibited in Section 1 unless inside a
direct quote from an attached file:

  - "live"
  - "deployed" (except when quoting HANDOFF.md)
  - "operational"
  - "exists in production"
  - "code executes against"
  - "running"
  - "handles"
  - "enforces"
  - "processes"
  - "dispatches"
  - "manages"
  - "validates" (when describing behavior, not visible logic)
  - any present-tense assertion of production runtime state not
    sourced from HANDOFF.md

## Section 2 Schema: Confirmed Executor Protocol Understanding

Prose or list. Must address all of the following:

- Execution model: GPT plans, Claude executes, Steven operates
- Source Truth Guarantee definition and stop-work trigger
- Delivery Contract requirement
- Four Quality Gates requirement
- Prohibited practices (placeholders, TODOs, stubs)
- PowerShell-only terminal commands
- Post-handback stop rule
- No memory/inference substitution rule

## Section 3 Schema: Exact Handback Format

Reproduce verbatim the handback template. Must include these sections
in this order:

  ### Task
  ### Delivered
  ### Deployment
  ### Verification
  ### Issues
  ### Quality Gate Results
      (must name all four gates: Build, Proof, Operations, Architecture)
  ### Delivery Contract Compliance
  ### GPT State Update

## Section 4 Schema: Inconsistencies and STOP-WORK Blockers

Every entry must be classified as exactly one of:

  CONFLICT -- two specific attached files contain directly
              contradictory statements about the same fact.
              The entry must name both files and cite the
              specific contradictory statements from each.

  GAP      -- a specific attached file is silent on information
              required for execution. The entry must name the
              file and state exactly what information is absent.

No entry may use any other classification. No entry may assert a
blocker based on inference, expectation, or general best practice.
Every entry must be traceable to a specific element in a specific
attached file.

An entry may be labeled stop-work only if the conflict or gap
directly prevents execution of the described task. An entry that
does not block execution must still be classified CONFLICT or GAP
but must be labeled non-blocking.

If no conflicts or gaps exist, state exactly: "None detected."

### Prohibited Phrases in Section 4

The following phrases are prohibited in Section 4 unless they appear
inside a direct quote from an attached file:

  - "authored"
  - "implemented"
  - "crafted"
  - "built"
  - "developed"
  - "written" when used to assert completion or quality rather than
    mere presence of text
  - any word that asserts intent, quality, or verified completion
    beyond what is directly visible in the attached file content

When referring to source files in Section 4, use "attached source
files" or the exact filename. Do not use characterizations that go
beyond neutral description of file presence and visible content.
