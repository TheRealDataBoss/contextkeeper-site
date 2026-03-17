# CHAT-BOOTSTRAP.md
# contextkeeper.org
# Generated: 2026-03-17

## Canonical Source of Truth
- GitHub repo: TheRealDataBoss/contextkeeper-site
- GitHub is the canonical source of truth
- cPanel is deployment target only

## Current Live Product State
- Marketing pages live
- Auth system live
- Security hardening complete
- Stripe billing complete
- Password reset flow complete
- Google OAuth endpoint exists but still depends on external credential configuration
- Bundle Generation Engine complete
- Connector Integrity Tier A complete
- API-accepted connector types are fully covered by implemented classes
- UI catalog still exposes more connector types than backend supports

## Connector State
See CONNECTOR-INVENTORY.md for authoritative counts and gap analysis.

## Workflow Protocol
See ORCHESTRATION-PROTOCOL.md.
Non-negotiable:
- GPT = orchestrator
- Claude = executor
- Steven = operator
- Claude must not plan future work
- Claude must output HANDBACK only after execution
- All HANDBACKs must include cPanel deployment steps

## Immediate Priority
Before Sprint 9 feature work, harden chat/session handoff so a new Claude chat can be brought into context deterministically.

## Next Expected Behavior
A new Claude chat should:
1. read attached files
2. acknowledge current project state
3. wait for a GPT execution prompt
4. not infer missing context from memory
