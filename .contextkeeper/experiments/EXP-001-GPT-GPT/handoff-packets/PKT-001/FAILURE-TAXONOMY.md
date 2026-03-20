# FAILURE-TAXONOMY.md

## Purpose

This file defines the controlled failure classes for EXP-001 GPT-to-GPT
handoff validation. Every failed run should map to one primary failure
class and may optionally list secondary contributing classes.

## Primary Failure Classes

- TRANSPORT_MISSING_FILES
- TRANSPORT_STALE_FILES
- TRANSPORT_WRONG_CHANNEL
- BOOTSTRAP_PROMPT_NONCOMPLIANCE
- SECTION_1_LABEL_FAILURE
- SECTION_1C_STRUCTURAL_FORM_FAILURE
- SECTION_1C_MULTI_FILE_INFERENCE
- SECTION_1C_BANNED_PHRASING
- SECTION_1C_ABSENCE_TO_BEHAVIOR_INFERENCE
- SECTION_4_MISCLASSIFICATION
- SECTION_4_BLOCKER_OVERREACH
- SCHEMA_NONCOMPLIANCE
- HANDOFF_FORMAT_NONCOMPLIANCE
- MODEL_MEMORY_LEAKAGE
- CONNECTOR_RETRIEVAL_MISMATCH
- PROJECT_FILE_CONTEXT_MISMATCH
- UNKNOWN_OTHER

## Instructions

1. Assign one primary failure class per failed run.
2. Add secondary classes only if they materially contributed.
3. Do not invent new class names mid-series.
4. If a new class is truly required, revise this file and increment the
   artifact set version before continuing the series.

