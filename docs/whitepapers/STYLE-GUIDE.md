# ContextKeeper White Paper Series: Authorship and Style Guide

> This document governs the production of all white papers in the ContextKeeper
> Enterprise Engineering White Paper Series. Any model, agent, or human author
> producing a paper in this series must read and follow this guide in its entirety.
> Deviations require explicit approval from the Series Editor.
>
> Version: 1.0
> Last updated: 2026-03-18
> Classification: Internal Production Reference

---

## 1. Series Identity

**Series Title:** ContextKeeper Enterprise Engineering White Paper Series

**Author:** Steven Wazlavek

**Organization:** ContextKeeper (contextkeeper.org)

**Series Editor:** Steven Wazlavek

**Contact:** masterboss@contextkeeper.org

**Series Numbering:** Papers are numbered sequentially (WP-001 through WP-NNN).
Each paper has a permanent number that does not change. New editions increment
the version (e.g., WP-001 v2.0), not the paper number.

### 1.1 Paper Registry

| Number | Title | Domain | Version | Status |
|--------|-------|--------|---------|--------|
| WP-001 | DEL-v2 Technical Reference: Deterministic Engineering Loop | Core protocol, state machine, bundle system, integrity verification | 1.0 | In Production |
| WP-002 | Enterprise Delivery Governance for AI-Orchestrated Software Engineering | Delivery contracts, quality gates, source truth, task schema, model intelligence, consensus, extended assurance | 1.0 | Published |
| WP-003 | Platform Architecture and Token Intelligence | Token optimization, planning mode, NL interface, task prioritization, context health | 1.0 | Planned |
| WP-004 | Open Infrastructure and AI Toolkit Reference | Open-weight LLMs, ML pipelines, RAG, vector DBs, chat UI, hosting, BYOK | 1.0 | Planned |
| WP-005 | Agent Orchestration, Marketplace, and Revenue Architecture | Agent builder, execution engine, marketplace, connector-powered agents, revenue model | 1.0 | Planned |
| WP-006 | Master Execution Plan and Phased Delivery Roadmap | Build phases, dependency graph, resource requirements, current state, remaining work | 1.0 | Planned |

### 1.2 Domain Ownership Rules

Each paper owns a distinct domain. No concept may be fully defined in more than
one paper. Cross-references are permitted and encouraged ("see WP-002, Section 3.1
for the Enterprise Delivery Contract specification"), but the authoritative
definition lives in exactly one place. If a concept appears to belong in two
papers, the Series Editor decides which paper owns it.

---

## 2. Authorship and Intellectual Property

### 2.1 Cover Page Format (Required)

Every paper begins with a cover page containing exactly these elements,
in this visual order, centered:

```
CONTEXTKEEPER
Enterprise Engineering White Paper Series

[PAPER TITLE]
[SUBTITLE]

WP-[NUMBER]  |  Version [X.Y]  |  [Month Year]

Author: Steven Wazlavek
Organization: ContextKeeper
Contact: masterboss@contextkeeper.org
Web: contextkeeper.org

Classification: [Confidential / Internal Engineering Reference / Public]
```

The cover page uses the following typography:
- "CONTEXTKEEPER": accent color, letter-spaced, small caps feel
- Series subtitle: medium gray, regular weight
- Paper title: largest text, navy/dark, bold
- Subtitle: large text, navy/dark, bold
- Metadata: small text, medium gray

No logos, no decorative elements, no background images. Clean, authoritative,
minimal. The formatting communicates seriousness through restraint.

### 2.2 Copyright and IP Notice (Required, Page 2)

Every paper must include the following notice on the second page, before
the table of contents. This text is fixed and must not be modified except
to fill in the bracketed fields.

---

**COPYRIGHT AND INTELLECTUAL PROPERTY NOTICE**

Copyright (c) [YEAR] Steven Wazlavek. All rights reserved.

This document and the ideas, architectures, protocols, algorithms, and
systems described herein constitute original intellectual property of
Steven Wazlavek, published under the ContextKeeper organization.

No part of this document may be reproduced, distributed, transmitted,
or used to create derivative works without the express written permission
of the author, except for brief quotations in critical reviews and
academic citations with proper attribution.

**STATEMENT OF ORIGINAL AUTHORSHIP**

The concepts, architectures, and systems described in this white paper
are the original work of Steven Wazlavek, developed during the design
and production engineering of the ContextKeeper platform
(contextkeeper.org). Where established academic concepts, algorithms,
or methodologies are referenced, they are cited explicitly. All novel
combinations, applications, extensions, and system designs presented
herein are original contributions.

**PRIOR ART DECLARATION**

This document is dated [FULL DATE] and constitutes a dated record of
the described intellectual property. The ContextKeeper platform, its
governance protocols, its architectural designs, and its operational
methodologies have been under continuous development since March 2026,
with version-controlled records maintained in the project repository
(github.com/TheRealDataBoss/contextkeeper-site).

**CONFIDENTIALITY**

This document is classified as [CLASSIFICATION]. Distribution is
restricted to authorized recipients as determined by the author.
Unauthorized distribution is prohibited.

---

### 2.3 AI-Assisted Production Disclosure

When a paper is produced with AI tool assistance (Claude, GPT, or other
models), the authorship attribution remains "Steven Wazlavek" as the
intellectual author. AI tools are production instruments, equivalent to
word processors, diagramming tools, or statistical software. They are
not co-authors. This is consistent with standard practice in academic
and technical publishing. No AI model name appears in authorship lines.

If a publication venue requires AI-use disclosure, add the following
footnote to the copyright page: "Production of this document was
assisted by AI language models used as writing and analysis tools
under the direction of the author."

---

## 3. Writing Standard

### 3.1 Target Audience

**Primary audience:** Senior technical leadership at enterprise
organizations. VPs of Engineering, Principal Engineers, Staff Engineers,
Distinguished Engineers, CTOs, and technical architects responsible for
AI-assisted development workflows at scale. These readers evaluate
systems for adoption. They will scrutinize every claim. They have seen
hundreds of whitepapers and can distinguish substance from marketing
in the first paragraph.

**Secondary audience:** Academic researchers in software engineering,
distributed systems, operations research, formal methods, and AI
safety. These readers evaluate novelty and rigor. They will check
formal definitions for correctness and citations for accuracy.

Every sentence must satisfy both audiences simultaneously. Precise
enough for a researcher to verify. Actionable enough for an
engineering leader to implement.

### 3.2 Quality Bar

The standard is this: if the paper were submitted as a technical report
to a top-tier computer science department, it would be accepted without
requests for additional rigor. If it were presented to a Fortune 500
CTO, it would be accepted without requests for additional detail. If
it were given to a competing AI model to critique, the model would find
nothing substantive to improve.

This standard is not aspirational. It is the minimum for inclusion in
the series. Papers that do not meet it are revised until they do.

### 3.3 Register and Tone

**Register:** Formal academic with engineering pragmatism. The voice of
IEEE Transactions on Software Engineering, ACM Computing Surveys, or
NIST Special Publications.

**Voice:** Third person for exposition. Passive voice where the agent
is irrelevant ("the invariant is verified" rather than "the system
verifies the invariant" when the focus is on the invariant, not the
system). Active voice for prescriptive statements ("the executor must
halt", not "halting should be considered"). First person plural ("we
define", "we observe") only in the executive summary and conclusion
for authorial framing.

**Precision requirements:**
- Every claim must be supported by formal definition, empirical
  evidence, or explicit derivation
- No qualitative assertions without quantitative backing where
  measurement is possible
- Technical terms must be defined on first use or in the glossary
- Acronyms must be expanded on first use in each paper
- Mathematical notation must follow standard conventions (set theory,
  predicate logic, complexity notation) and be defined when introduced
- All tables must have descriptive headers and be self-contained
  (readable without surrounding prose)

### 3.4 Prohibited Language

The following terms and patterns are prohibited in all papers:

**Methodology jargon:**
- "Sprint" or "Scrum" or "standup" or "retrospective" or any
  Agile-specific vocabulary. Use: "development iteration,"
  "delivery cycle," "engineering phase."
- "Best practices" (unverifiable). Use: "established methods,"
  "validated approaches," or cite specific sources.
- "Industry standard" without a citation to the actual standard.

**Marketing language:**
- "Cutting edge," "next generation," "game changer," "paradigm shift,"
  "revolutionary," "disruptive," "world-class"
- "State of the art" without a comparative analysis supporting the claim
- "Simply," "just," "easily" (minimizes complexity, undermines
  credibility with expert readers)
- "Robust" without specifying what failure modes it withstands
- "Scalable" without specifying the scaling dimension and tested range

**Typographic prohibitions:**
- Em dashes. Use commas, semicolons, colons, or separate sentences.
- Exclamation marks. No exceptions.
- Contractions. Write "does not," not "doesn't."
- Emoji or decorative Unicode characters in body text

**Structural prohibitions:**
- No placeholder text, TODO markers, or "to be determined" sections
- No forward references to unpublished papers without noting the
  planned publication
- No internal project references (task IDs, chat names, connector
  IDs, specific database credentials, personal names of team members
  in operational context)

### 3.5 Structural Template

Every paper follows this section order. Sections may be omitted only
if genuinely not applicable (e.g., a purely theoretical paper may
omit Implementation Evidence), with a note in the table of contents.

1. **Cover Page** (title, series identity, authorship, classification)
2. **Copyright and IP Notice** (page 2, verbatim from Section 2.2)
3. **Table of Contents** (generated from headings)
4. **Executive Summary** (1-2 pages maximum; states the problem, the
   contribution, and the key results; a reader who reads only this
   section should understand what the paper offers and why it matters)
5. **Problem Statement** (what problem does this paper address; must
   include evidence that the problem is real, not hypothetical;
   quantified impact where possible)
6. **Core Content Sections** (the substance of the paper; numbered
   sequentially; depth and subdivision as needed)
7. **Implementation Evidence** (concrete evidence from production
   systems; failure catalogs; measured outcomes; this section
   distinguishes the paper from theoretical proposals)
8. **Integration Points** (how this paper's contributions connect to
   other papers in the series; cross-references to specific sections)
9. **Conclusion** (summary of contributions; restatement of the key
   mechanisms and their failure-mode coverage; forward-looking
   statement limited to planned work, not speculation)
10. **Appendices** (glossary, formal proofs, extended data tables,
    schema definitions; material that supports the paper but would
    interrupt the flow of the main argument)
11. **References** (cited works, standards, and prior art)

### 3.6 Heading Hierarchy

- **H1:** Section numbers and titles. Example: "3. Source Truth Guarantee"
- **H2:** Subsection numbers and titles. Example: "3.2 Stop-Work Protocol"
- **H3:** Sub-subsection. Example: "3.2.1 Missing File Detection"
- No deeper than H3. If H4 seems necessary, restructure the content.

Heading titles must be descriptive and specific. "Overview" and
"Details" are prohibited as heading titles. Every heading must convey
what the section contains without reading it.

### 3.7 Tables

Tables are a primary exposition mechanism in this series. They must be:
- Self-contained: readable without surrounding prose
- Consistently formatted across all papers
- Column headers in bold, navy background, white text
- Alternating row shading for readability
- No merged cells
- Every table referenced in the preceding prose

### 3.8 Callout Blocks

Important principles, key insights, and critical distinctions are
highlighted in callout blocks (left-bordered, indented, italic, navy
text). Use sparingly: no more than 3-4 per major section. Overuse
dilutes their impact.

### 3.9 Cross-Paper References

When referencing another paper in the series, use this format:
"(see WP-002, Section 3.1)" or "as defined in the Enterprise Delivery
Governance paper (WP-002)." Do not reproduce content from another paper;
reference it. If the referenced paper is not yet published, note:
"(WP-003, planned)."

---

## 4. Document Production

### 4.1 File Format

Papers are produced as .docx files using the docx-js library (Node.js).
The build script for each paper is stored alongside the output for
reproducibility. The .docx format is chosen for compatibility with
enterprise document workflows (legal review, board distribution,
regulatory submission).

A markdown (.md) version is also maintained in the repository for
version control and diffing. The .docx is the authoritative format
for distribution; the .md is the authoritative format for content
management.

### 4.2 Typography

- **Body font:** Arial, 11pt (22 half-points in docx-js)
- **H1:** Arial, 18pt, bold, navy (#1B2A4A)
- **H2:** Arial, 14pt, bold, accent blue (#2E75B6)
- **H3:** Arial, 12pt, bold, dark gray (#2C2C2C)
- **Body text:** Arial, 11pt, dark gray (#2C2C2C)
- **Line spacing:** 1.15x (276 in docx-js units)
- **Paragraph spacing:** 200 after

### 4.3 Color Palette

| Use | Color | Hex |
|-----|-------|-----|
| Headings (H1), table headers, callout text | Navy | #1B2A4A |
| Headings (H2), accent borders, callout borders | Accent Blue | #2E75B6 |
| Body text, Headings (H3) | Dark Gray | #2C2C2C |
| Metadata, headers/footers | Medium Gray | #555555 |
| Table alternate rows | Light Background | #F2F6FA |
| Table header text | White | #FFFFFF |

### 4.4 Page Layout

- **Page size:** US Letter (8.5" x 11")
- **Margins:** 1" all sides (1440 DXA)
- **Content width:** 6.5" (9360 DXA)
- **Header:** Right-aligned, italic, medium gray:
  "ContextKeeper  |  [Paper Short Title]  |  WP-[NUMBER] v[X.Y]"
- **Footer:** Centered, medium gray:
  "Page [N]  |  Confidential"

### 4.5 Production Checklist

Before a paper is considered complete, verify:

- [ ] Cover page matches template exactly
- [ ] Copyright and IP notice is present and complete on page 2
- [ ] Table of contents is present and accurate
- [ ] All sections from the structural template are present or
      explicitly noted as not applicable
- [ ] All heading numbers are sequential and correct
- [ ] All tables have headers, are self-contained, and are referenced
      in prose
- [ ] All callout blocks are used appropriately (key insights only)
- [ ] All cross-paper references use the correct WP-NNN format
- [ ] No prohibited language appears anywhere in the document
- [ ] No internal project references (task IDs, names, credentials)
- [ ] No placeholder text, TODOs, or incomplete sections
- [ ] Glossary is present and covers all technical terms
- [ ] References section is present (even if only "No external
      references" for papers based entirely on original work)
- [ ] Document validates cleanly (no XML errors in .docx)
- [ ] Markdown version matches .docx content

---

## 5. Content Quality Gates

### 5.1 Rigor Gate

Every factual claim in the paper must pass one of these tests:
- **Formal definition:** The claim is a defined property of a
  formally specified system (e.g., "the state machine has 10 states")
- **Empirical evidence:** The claim is supported by measured data
  from a production system (e.g., "correction cost was 4x original")
- **Derivation:** The claim follows logically from stated premises
  (e.g., "Expected Risk = P(failure) x Cost(failure)")
- **Citation:** The claim references an established result from
  the literature

Claims that pass none of these tests must be removed or rewritten
as hypotheses with explicit qualification.

### 5.2 Completeness Gate

Every concept introduced in the paper must be:
- Defined (what it is)
- Motivated (why it exists, what problem it solves)
- Specified (how it works, with enough detail to implement)
- Bounded (what it does not do, what its limitations are)

A concept that is introduced but not fully specified violates the
series' anti-placeholder principle. Either complete it or remove it.

### 5.3 Consistency Gate

Every paper must be consistent with:
- All other published papers in the series (no contradictions)
- The current system state (no claims about features that do not exist
  without noting they are planned)
- Standard terminology (terms must mean the same thing across all papers)

### 5.4 Independence Gate

Every paper must be readable as a standalone document by a reader who
has not read any other paper in the series. Cross-references provide
depth, not prerequisites. A reader should be able to understand the
core argument of any single paper without reading the others.

---

## 6. Revision Protocol

### 6.1 Version Numbering

- **Major version (X.0):** Structural changes, new sections added,
  significant content revisions
- **Minor version (X.Y):** Corrections, clarifications, updated
  evidence, formatting fixes

### 6.2 Change Tracking

Every revision must include a changelog entry in the paper's front
matter or appendix:

```
REVISION HISTORY

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-03-18 | Initial publication |
| 1.1 | 2026-XX-XX | [Description of changes] |
```

### 6.3 Cross-Paper Impact

When a paper is revised, check all other published papers for
cross-references to the revised content. Update cross-references
if section numbers have changed. Notify the Series Editor of any
changes that affect other papers' content.

---

## 7. Instructions for AI Model Production

When an AI model (Claude, GPT, or other) is tasked with producing
or revising a paper in this series, the following instructions apply
in addition to all rules above.

### 7.1 Pre-Production

1. Read this entire style guide before writing any content
2. Read the paper registry (Section 1.1) to understand domain boundaries
3. If revising an existing paper, read the current version in full
4. If producing a new paper, read at least the executive summary of
   all published papers to ensure no domain overlap

### 7.2 During Production

1. Follow the structural template exactly (Section 3.5)
2. Check every sentence against the prohibited language list (Section 3.4)
3. Check every claim against the rigor gate (Section 5.1)
4. Use the exact typography and color specifications (Sections 4.2-4.3)
5. Generate the .docx using docx-js with the specified styles
6. Do not abbreviate, summarize, or defer any section. Every section
   in the template must be fully realized.

### 7.3 Post-Production

1. Run the production checklist (Section 4.5)
2. Validate the .docx file
3. Verify heading numbers are sequential
4. Verify all tables are complete and referenced
5. Verify no prohibited language appears
6. Produce the markdown version for repository storage

### 7.4 Continuity Across Sessions

If production spans multiple chat sessions:
1. The style guide must be attached to every session
2. The current draft (if partial) must be attached
3. The producing model must confirm it has read both before continuing
4. Output quality must be indistinguishable between sessions
5. No model should be able to identify which sections were produced
   in which session by reading the final document

---

## 8. Distribution and Publication

### 8.1 Internal Distribution

Papers classified as "Internal Engineering Reference" or "Confidential"
are distributed only to:
- The engineering team (all roles in the orchestration protocol)
- Authorized investors or advisors under NDA
- Legal counsel for IP review

### 8.2 Public Distribution

Papers classified as "Public" may be distributed via:
- contextkeeper.org/whitepapers/
- DataDedicated.com (author's professional portfolio)
- Academic preprint servers (arXiv, SSRN) with proper formatting
- Conference proceedings with proper submission formatting
- LinkedIn and professional networks as linked PDFs

### 8.3 Citation Format

When citing papers from this series in external publications:

```
Wazlavek, S. (2026). [Paper Title]. ContextKeeper Enterprise
Engineering White Paper Series, WP-[NUMBER], v[X.Y].
contextkeeper.org.
```

When citing within the series, use the short format:
"(WP-[NUMBER], Section X.Y)"
