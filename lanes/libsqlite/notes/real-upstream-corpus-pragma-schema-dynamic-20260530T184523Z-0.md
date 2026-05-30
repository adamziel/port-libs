# Real Upstream Corpus: PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T184523Z-0`

Accepted base: `7e63d4798cb030955a466f3272d59cba9c03648e`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
  - `schema3-1.*` multi-client schema cache refresh after table creation
  - `schema3-1.7` through `schema3-1.13` ALTER TABLE ADD COLUMN cache refresh
  - `schema3-1.14` through `schema3-1.16` index and trigger drop/recreate cache refresh
  - `schema3-1.17` through `schema3-1.18` view cache refresh after view/table-column changes
  - `schema3-1.19` through `schema3-1.22` drop-if-exists/recreate replacement DDL refresh

Behavior ported:

- Added a focused dynamic corpus that exercises existing schema DDL reparse behavior for the upstream `schema3.test` stale-schema-cache refresh class. The cases use generic application table/view/index/trigger names and verify that schema cookies advance, prepared statements are invalidated, new objects are visible through the catalog, altered columns appear through `PRAGMA table_info`, replacement indexes/triggers supplant stale records, and dropped/recreated views are reflected in the schema record set.

Focused coverage:

- `SQLiteRealUpstreamPragmaSchema3DynamicTest.php`
  - 250 dynamic create-table/index/trigger refresh cases
  - 250 dynamic ALTER TABLE ADD COLUMN refresh cases
  - 250 dynamic index/trigger replacement refresh cases
  - 250 dynamic view drop/recreate refresh cases
- Focused PASS cases: 1000
- Behavior assertions: 6000

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema3DynamicTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema3DynamicTest.php` -> `1 test files, 6000 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: `343392 -> 344392` (`+1000` focused PASS lines)
- `mapped coverage`: unchanged at `1189 / 1589`; this is behavior-backed PHP PASS-line growth, not denominator admission.

Dependency closure:

- No new support component is needed. This reuses lane-local schema DDL reparse and PRAGMA schema catalog primitives.

Non-overlap:

- This does not repeat the earlier `schema.test`/`schema2.test` rollback-expired same-cookie batch or the prior `pragma.test`/`pragma3.test` table-info/index/data-version batch.
- This does not touch source-neutral cleanup, runner-map denominator rows, VFS/pager surfaces, or domain-specific API surfaces.
