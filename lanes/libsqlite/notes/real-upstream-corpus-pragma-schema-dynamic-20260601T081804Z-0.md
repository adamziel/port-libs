# Real Upstream Corpus: PRAGMA Schema Prepare Tail Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260601T081804Z-0`
Base accepted HEAD: `cd382f66a9c80c833a3567dcc34622923a1e8fb9`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/capi3.test`
  - `capi3-2.6`: `sqlite3_prepare16` accepts `PRAGMA table_info("TableName"); --excess text` as the first statement and steps to `SQLITE_ROW`.
  - `capi3-2.7`: the next step reaches `SQLITE_DONE`.
  - `capi3-2.8`: finalizing the statement succeeds.

## Implemented Behavior

- `SQLitePragmaSchemaCatalog::parsePragma()` and `parseTableValuedPragma()` now parse only the first SQL statement before the first unquoted semicolon.
- The first-statement scanner preserves semicolons inside single-quoted, double-quoted, backtick-quoted, and bracket-quoted identifiers/literals, and skips line and block comments while searching for the statement boundary.
- Added `SQLiteRealUpstreamPragmaSchemaPrepareTailDynamicTest.php` with 1000 dynamic TestRunner cases plus one source-citation case covering direct and table-valued schema PRAGMAs:
  - `table_info`
  - `table_xinfo`
  - `index_list`
  - `index_info`
  - `index_xinfo`
  - `foreign_key_list`

## Non-Overlap

This slice owns `capi3.test` `capi3-2.6` through `capi3-2.8` prepare-tail behavior for PRAGMA schema statements only.

It avoids accepted `pragma.test` table info/index xinfo/schema-version behavior, `pragma4.test` result-shape/table-valued joins/corrupt-view behavior, `pragma5.test` virtual metadata behavior, schema-cache reload, trusted schema, lock proxy file, cache/page-count/data-version, VFS, WAL, B-tree, SELECT, JSON, and source-neutral cleanup clusters.

## Red-First Evidence

Pre-fix parser probe rejected the upstream shape:

`PRAGMA table_info("TableName"); --excess text`

with:

`InvalidArgumentException: Only PRAGMA table_info, table_xinfo, index_list, index_info, index_xinfo, foreign_key_list, table_list, function_list, module_list, collation_list, and pragma_list are supported`

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaPrepareTailDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaPrepareTailDynamicTest.php`
  - `1 test files, 7505 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaPrepareTailDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicJoinCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorruptViewTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicGeneratedXinfoTest.php`
  - `6 test files, 62175 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - no whitespace errors
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- Root harness:
  - not run - isolated micro-slice

## Expected Dashboard Movement

- `phpPass`: `5762618 -> 5763619` (`+1001` real TestRunner PASS lines)
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`

## Dependency Closure

No new support component is needed. This reuses native `SQLitePragmaSchemaCatalog`, `SQLiteAttachedSchemaCatalog`, and `SQLitePragmaRowCursor`; the source parser fix is lane-local.
