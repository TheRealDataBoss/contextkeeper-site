# CONNECTOR-INVENTORY.md
# Authoritative Source of Truth
# Generated: 2026-03-17 from live cPanel codebase

## Summary

| Layer | Count |
|-------|-------|
| Dashboard UI catalog (connectorTypes array) | 97 unique types |
| API validTypes (POST /connectors accepts) | 20 types |
| Implemented PHP connector classes | 20 classes |

## Implemented Connector Classes
github
google_drive
s3
postgresql
local_file
gitlab
bitbucket
dropbox
onedrive
azure_blob
bigquery
snowflake
mongodb
redis
notion
slack
jira
supabase
cloudflare_r2
hugging_face

## Gap Analysis
- UI catalog still exceeds backend implementation coverage
- Remaining UI-only connector types without backend remain out of scope until explicitly prioritized
- Product integrity priority after Tier A is UI-backend contract alignment
