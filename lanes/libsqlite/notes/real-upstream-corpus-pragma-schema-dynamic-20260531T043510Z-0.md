# real-upstream-corpus-pragma-schema-dynamic-20260531T043510Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`.
- Ported sections: `pragma2-4.4`, `pragma2-4.5.1`, `pragma2-4.5.2`, `pragma2-4.6`, and `pragma2-4.8`.
- Behavior: added `SQLitePragmaRuntimeState::pragmaLockStatus()` for PRAGMA-facing lock-status rows. It preserves the existing internal temp state as `closed`, while reporting `temp unknown` through PRAGMA output, matching upstream `PRAGMA lock_status` rows around cache-spill pager locking.
- Focused PHP coverage: `SQLiteRealUpstreamPragma2LockStatusDynamicTest.php` adds 1,251 focused TestRunner PASS cases over dynamic generic schemas, cache-spill OFF/ON/high-threshold lock promotion, attached-schema inheritance, and result row ordering.
- Non-overlap: this does not repeat accepted cache-spill threshold value tests, schema-version/user-version state tests, pager cache-spill recovery, VFS lock-state/process-lock models, or PRAGMA schema catalog/table-valued rows. The new surface is the user-facing `PRAGMA lock_status` row shape for the real `pragma2.test` cache-spill lock transitions.
- Dependency closure: no new support component is needed; the slice reuses the existing runtime PRAGMA state and dirty-page lock model.
