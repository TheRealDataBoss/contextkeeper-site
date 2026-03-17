# DEFINITIVE-AUDIT.md
# contextkeeper.org - Source of Truth
# Owner: GPT (Orchestrator)
# Last updated: 2026-03-17 by Claude (Sprint 5 sync)

## File Manifest

### Marketing Pages (public_html/)
| File | Lines | Status |
|------|-------|--------|
| index.html | 907 | LIVE - Login in nav |
| features.html | 293 | LIVE - Login in nav |
| pricing.html | 248 | LIVE - Login in nav |
| how-it-works.html | 1001 | LIVE - Login in nav |
| connectors.html | 602 | LIVE - Login in nav |
| docs.html | 217 | LIVE - Login in nav |
| about.html | 161 | LIVE - Login in nav |
| use-cases.html | 476 | LIVE - Login in nav |
| teams.html | 517 | LIVE - Login in nav |
| enterprise.html | 188 | LIVE - Login in nav |

### Auth (public_html/app/auth/)
| File | Status | Sprint |
|------|--------|--------|
| login.php | LIVE - CSRF, rate limit, remember me, forgot pw link | S2+S4 |
| register.php | LIVE - bcrypt, auto API key | S2 |
| logout.php | LIVE | S2 |
| forgot-password.php | LIVE - hashed tokens, Resend API | S4 |
| reset-password.php | LIVE - token validation, single-use | S4 |
| google-oauth.php | LIVE - awaiting credentials | S4 |

### Dashboard (public_html/app/dashboard/)
| File | Status | Sprint |
|------|--------|--------|
| index.php | LIVE - projects, stats | S2 |
| project.php | LIVE - project detail | S2 |
| connectors.php | LIVE - 67 types, add/delete/test | S3 |
| billing.php | LIVE - responsive plan grid (CSS fixed) | S2+S4 |
| settings.php | LIVE - profile, API key, password | S2 |

### API (public_html/app/api/v1/)
| File | Status | Sprint |
|------|--------|--------|
| index.php | LIVE - router, CORS, rate limiting | S2 |
| status.php | LIVE | S2 |
| projects.php | LIVE | S2 |
| sync.php | LIVE | S2 |
| bootstrap.php | LIVE | S2 |
| decisions.php | LIVE | S2 |
| invariants.php | LIVE | S2 |
| bundles.php | LIVE | S2 |
| sessions.php | LIVE | S2 |
| connectors.php | LIVE | S2 |
| usage.php | LIVE | S2 |
| webhooks/stripe.php | LIVE | S2 |

### Libraries (public_html/app/lib/)
| File | Purpose |
|------|---------|
| Auth.php | Session + API key auth, plan limits |
| Csrf.php | Token generation + validation |
| Database.php | PDO singleton |
| Encryption.php | AES-256-CBC, per-user key derivation |
| LoginLimiter.php | IP-based rate limiting |
| StripeHelper.php | Checkout, portal, webhooks, sync |
| Validator.php | Input sanitization + type checking |

### Connectors (public_html/app/connectors/)
| File | Type Key | Status | Sprint |
|------|----------|--------|--------|
| ConnectorInterface.php | - | LIVE | S2 |
| GithubConnector.php | github | LIVE | S5 |
| S3Connector.php | s3 | LIVE | S5 |
| Google_driveConnector.php | google_drive | LIVE | S5 |
| PostgresqlConnector.php | postgresql | LIVE | S5 |
| Local_fileConnector.php | local_file | LIVE | S5 |

### Database
| Table | Purpose | Sprint |
|-------|---------|--------|
| users | Accounts, plans, Stripe IDs, API keys | S1 |
| projects | Project state tracking | S1 |
| sessions_log | Sync session history | S1 |
| decisions | Architectural decisions | S1 |
| invariants | Project invariants | S1 |
| connectors | User connector configs (encrypted) | S1 |
| usage_log | API call + action tracking | S1 |
| webhook_events | Stripe webhook idempotency | S2 |
| password_resets | Reset tokens (hashed, expiring) | S4 |

### Config & Security
| File | Location | Notes |
|------|----------|-------|
| config.php | app/config.php | DB creds, Stripe keys, APP_SECRET |
| .htaccess | public_html/.htaccess | cPanel PHP handler |
| .htaccess | app/.htaccess | API routing, CSP, security headers |
| schema.sql | app/schema.sql | Base schema (7 tables) |
| 002_stripe_billing.sql | app/migrations/ | webhook_events + subscription columns |
| 003_password_reset.sql | app/migrations/ | password_resets + Google columns |
