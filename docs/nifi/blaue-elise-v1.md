# Blaue Elise v1 – NiFi Outline
## Parameter Context
- DB_CAP_CONN (DBCPConnectionPool → repweb DB)
- VCENTER_URL / USER / PASS (if applicable)
- CAPTURE_SCHEMA = repweb_capture
- MIG_SCHEMA = repweb_mig
- PI_SCHEMA = repweb_pi
- ROCKETCHAT_WEBHOOK (optional)
## Pipelines
1) vCenter Inventory → Landing
   - GetVMwareInventory (or ExecuteScript if custom)
   - PutDatabaseRecord → landing_vcenter_vm.raw (JSON)
   - ReportLineage (provenance metadata)
2) Curate CI (daily)
   - QueryRecord on landing → normalize fields (name, env, tags)
   - ExecuteScript: triangulation weights + freshness decay
   - PutDatabaseRecord → cur_ci (upsert by ci_key)
3) Progress Indicators (nightly)
   - GenerateTableFetch (mig_event) window last 24h
   - ExecuteScript: compute completion_rate, rollback_rate, readiness_gate_pass_rate
   - PutDatabaseRecord → repweb_pi.progress_indicator (UPSERT by uq_idx)
4) Notifications (optional)
   - QueryDatabaseTable (mig_event etype in ['StepStart','StepDone','Rollback'])
   - InvokeHTTP → Rocket.Chat webhook
