# Trigger recursive view RETURNING current-source next203

- Added `SQLiteTriggerRecursiveViewReturningCurrentSourceNext203Plan` for recursive `INSTEAD OF` view-trigger `RETURNING` batches where the current-source recursive child rows must publish a generation handoff receipt before the next source becomes visible.
- Focused tests cover generation token matching, receipt acknowledgement, missing/unexpected/reordered receipts, next-source hold/release tagging, visible/held payloads, dependency metadata, and malformed receipt/token validation.
- Application smoke: `examples/application-trigger-recursive-view-returning-current-source-next203.php` exercises copied `wp_options` import rows where current recursive child `RETURNING` payloads are visible before `home`/`next_plugin` next-source rows.
- Dependency closure: no new support component is needed; this reuses the native recursive view/trigger current-source and next196 recursive-child drain model.
- Non-overlap: this adds generation-handoff fencing after next196 child drain and avoids accepted next196 child drain, next195 receipt fence, next191 fingerprint fencing, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters.
