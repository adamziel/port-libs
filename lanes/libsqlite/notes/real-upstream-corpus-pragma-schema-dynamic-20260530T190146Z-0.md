# real-upstream-corpus-pragma-schema-dynamic-20260530T190146Z-0

- Base accepted HEAD: `28d061295d83cf4ef005caf2fa1b98587d6f90d3`.
- Added `SQLiteRealUpstreamPragmaSchemaDynamicThousandTest.php` with 1,001 focused TestRunner cases.
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test` `pragma-6.2`, `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test` `pragma3-100` through `pragma3-190`, `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test` `pragma4-4.3` through `pragma4-4.5`, plus schema invalidation/stability families in `schema.test`, `schema2.test`, and `schema6.test`.
- Non-overlap: this batch uses a fresh `real upstream pragma schema dynamic thousand` test namespace and generic `tenant_settings_*` schema names. It does not touch accepted PRAGMA dynamic/followup/wide-batch files, runner metadata rows, WordPress-shaped APIs, or generated fake upstream script ids.
- Dependency closure: no new support component is needed; the batch reuses existing `SQLitePragmaSchemaCatalog`, `SQLitePragmaSchemaDataVersion`, and `SQLiteSchemaRecord` primitives.
- Expected dashboard movement: count as real PHP PASS-line growth for the new focused test file only; no mapped denominator change claimed.
