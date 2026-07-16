# real-upstream-corpus-pragma-schema-dynamic-20260531T012057Z-0

Scope: real upstream PRAGMA/schema dynamic corpus.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
- Ported scenario ranges:
  - `schema3-1.1` through `schema3-1.6`: create table / DML / create index cache refresh.
  - `schema3-1.14` through `schema3-1.16`: create/drop index and trigger cache refresh.
  - `schema3-1.17` through `schema3-1.22`: view/table/index/trigger replacement refresh.

Behavior covered:

- A connection with prepared statements using an old schema cookie must invalidate those statements after another connection changes the schema.
- Dynamic `app_settings_N`, `app_events_N`, and `app_audit_N` schemas cover create/drop/replace behavior for tables, indexes, views, and triggers.
- PRAGMA schema catalog rows are checked after each DDL operation to confirm that refreshed schema metadata matches the new object graph.

Focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema3RefreshTest.php`.
- Focused result: `1 test files / 9704 assertions / 0 failures`.
- PASS-line growth: `+1101` focused TestRunner PASS cases.
- `lane-status.json` `phpPass`: `1473407 -> 1474508`.
- Mapped denominator coverage remains `1589 / 1589`.

Non-overlap:

- This extends the pragma/schema dynamic corpus with upstream `schema3.test` multi-client schema refresh behavior.
- It does not repeat prior `pragma.test` table-info/index-info/FK cases, `pragma3` data-version cases, `pragma4` table-valued PRAGMA schema resolution, `pragma5` table-list/function/module rows, `schema.test` / `schema2.test` rollback-expired statement behavior, or existing shadowing/join/runtime PRAGMA batches.
- `ALTER TABLE ADD COLUMN` cases from `schema3.test` are intentionally excluded because `SQLiteSchemaDdlReparsePlan` does not yet model column mutation; that is a separate follow-up surface.

Dependency closure:

- No new support component is needed.
- This reuses lane-local `SQLiteSchemaDdlReparsePlan`, `SQLitePragmaSchemaCatalog`, and schema-cookie invalidation behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema3RefreshTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema3RefreshTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
