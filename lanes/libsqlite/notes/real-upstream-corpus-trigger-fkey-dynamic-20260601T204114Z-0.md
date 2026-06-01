# real-upstream-corpus-trigger-fkey-dynamic-20260601T204114Z-0

Status: ready for integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test`
- Ported range: `fkey1-1.0..3.5`

Behavior covered:

- `fkey1-1.0..1.2`: foreign-key declarations over a self-reference, a second parent table, and a composite parent-key reference are accepted into schema metadata.
- `fkey1-3.1..3.4`: `PRAGMA foreign_key_list` returns rows in SQLite declaration order, with composite `seq` rows preserved, implicit parent-column references rendered as an empty `to` column, and `ON UPDATE` / `ON DELETE` action text normalized.
- `fkey1-3.5`: with no open deferred violations, `DBSTATUS_DEFERRED_FKS` reports zero current and high-water counts.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan()` as a generic FK catalog model for `PRAGMA foreign_key_list`-style rows.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicFkey1Catalog20260601Test.php` with 10,789 focused TestRunner PASS lines and 11,238 behavior assertions over 220 seeded schema variants plus guard cases.
- Updated `lane-status.json` pending `phpPass` from `6253874` to `6264663` based on the measured focused PASS-line delta.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` => no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey1Catalog20260601Test.php` => no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey1Catalog20260601Test.php` => `1 test files, 11238 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey1Catalog20260601Test.php | awk 'BEGIN{pass=0} /^PASS /{pass++} END{print pass}'` => `10789`.

Non-overlap:

- This owns only early `fkey1.test` FK schema declaration and foreign-key-list catalog behavior.
- It avoids accepted `fkey1` quoted cascade, self-replace cascade, partial-index repair, wide `foreign_key_check`, and corrupt-stat schema behavior.
- It also avoids accepted `fkey2` DDL/action/counter/conflict sections, `fkey3` self-reference, `fkey5` foreign-key-check, `fkey6`/`fkey8` deferred/action behavior, trigger row-image/lifecycle batches, RETURNING/UPSERT, WAL/VFS/B-tree, JSON, PRAGMA/schema, and source-neutral cleanup slices.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local trigger/FK dynamic planner and the hydrated SQLite upstream checkout as source truth.

Root harness:

- Not run - isolated micro-slice.
