# real-upstream-corpus-pragma-schema-dynamic-20260531T210745Z-0

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-23.1`: a peer connection sees the initial schema with `t1`, `i1`, `i2`, `i2x`, `i3`, and `t2`.
- `pragma-23.2a`: after a peer drops and recreates `i2`, `PRAGMA index_info(i2)` reports refreshed key order `c,d,b`.
- `pragma-23.3`: after `i3` is recreated, `PRAGMA index_list(t1)` reports refreshed index order and created-index origins.
- `pragma-23.4`: `ALTER TABLE ... ADD COLUMN e` is visible through `PRAGMA table_info(t1)`.
- `pragma-23.5`: recreating `t2` with `y INTEGER REFERENCES t1` is visible through `PRAGMA foreign_key_list(t2)`.

## Patch

- Added `SQLitePragmaSchemaLiveReloadPlan`, a generic comparison helper for PRAGMA rowsets before and after schema-cookie refresh.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicLiveReload20260531Test.php` with 1000 dynamic upstream-backed behavior cases plus a source/dependency guard case.
- The test varies generic application table, child table, and index names while checking refreshed `index_info`, `index_list`, `table_info`, and `foreign_key_list` rows.

## Non-Overlap

This owns only upstream `pragma.test` `23.1`, `23.2a`, `23.3`, `23.4`, and `23.5` live PRAGMA schema reload behavior. It avoids accepted `pragma.test` 23.2b-23.2e `index_xinfo` key/auxiliary metadata, `pragma4.test` table-valued joins, schema2 prepared expiry, schema3 refresh, schema5/schema6, temp pager/cache/page-count/version, lock proxy, data-store-directory, VFS, WAL, B-tree, JSON, SELECT, and expression-affinity clusters.

## Dependency Closure

No new support component is needed. The slice reuses lane-local `SQLitePragmaSchemaCatalog` parsing/introspection and adds the smallest generic schema-cookie rowset comparison helper required for this upstream PRAGMA reload cluster.

## Focused Verification

- `php -l lanes/libsqlite/src/SQLitePragmaSchemaLiveReloadPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicLiveReload20260531Test.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicLiveReload20260531Test.php`: 1 test file, 7763 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test file, 3 assertions, 0 failures.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`: lane-status json ok.
- `git diff --check -- lanes/libsqlite`: passed with no output.

## Status Delta

- Focused PASS cases: +1001.
- Focused assertions: +7763.
- `lane-status.json` `phpPass`: 3847998 -> 3855761.
- Mapped coverage: unchanged at 1589 / 1589; the upstream denominator is already fully mapped.
- Root harness: not run - isolated micro-slice.
