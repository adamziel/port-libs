# real-upstream-corpus-trigger-fkey-dynamic-20260531T031000Z-0

Base accepted HEAD: `d3f35d53d135e23f73a270582d60d9916715bb54`

This slice ports a non-overlapping trigger/FK DDL behavior cluster from the
hydrated upstream SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- `fkey2-14.1.*`: `ALTER TABLE ADD COLUMN` with `REFERENCES` and non-NULL
  defaults under `PRAGMA foreign_keys`.
- `fkey2-14.2.*`: `ALTER TABLE RENAME TABLE` rewrites self-references and
  child-table FK parent references inside the same schema.
- `fkey2-14.3.*` and `fkey2-14.4.*`: `DROP TABLE` with FK child rows, missing
  parent references, view/virtual-parent references, and no-crash DDL behavior.

Changed files:

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDdlTest.php`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T031000Z-0.md`
- `lanes/libsqlite/lane-status.json`

Focused coverage:

- Adds `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyDdlPlan()` for bounded
  upstream fkey2-14 DDL diagnostics.
- Adds `SQLiteRealUpstreamTriggerFkeyDynamicDdlTest.php` with 4,706 focused
  TestRunner PASS cases and 5,228 assertions.
- Uses generic `app_*`, `tenant`-neutral table names only.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDdlTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDdlTest.php`
  - `1 test files, 5228 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing trigger/FK
  dynamic corpus plan surface and adds a bounded native PHP DDL diagnostic
  helper for the upstream `fkey2-14.*` behavior.

Non-overlap:

- Does not repeat accepted `fkey2-15/16/17`, fkey2 composite/nocase/replace,
  fkey3 self-reference, fkey4 autocommit, fkey5 foreign-key check, fkey6,
  fkey7, fkey8, trigger1 target class, trigger6 expression evaluation,
  triggerG recursion, row-value, upsert/returning, PRAGMA FK catalog, VFS,
  pager/WAL, JSON, B-tree, or source-neutral cleanup batches.
