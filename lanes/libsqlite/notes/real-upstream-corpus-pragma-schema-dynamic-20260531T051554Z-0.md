# real-upstream-corpus-pragma-schema-dynamic-20260531T051554Z-0

- Base accepted HEAD: `597c96169f44cb49bb577675ba5900812102b596`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  - `schema-1.*` CREATE/DROP TABLE invalidates prepared statements.
  - `schema-2.*` CREATE/DROP VIEW invalidates prepared statements.
  - `schema-3.*` CREATE/DROP TRIGGER invalidates prepared statements.
  - `schema-4.*` CREATE/DROP INDEX invalidates prepared statements.
  - `schema-5.*` ATTACH does not invalidate an existing prepared statement, DETACH does.
- PHP coverage: extended `SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` with 260 dynamic generic variants for each of the four behavior groups, adding 1,040 distinct TestRunner PASS cases.
- Focused assertion movement: the file moved from `1 test files / 20351 assertions / 0 failures` before this slice to `1 test files / 26071 assertions / 0 failures`, for `+5720` assertions.
- Expected selected PASS movement: `phpPass` moves from `2260947` to `2261987` if accepted. Mapped coverage remains `1589 / 1589`; this is additional behavior coverage over already mapped upstream schema/PRAGMA corpus files.
- Non-overlap: this does not repeat the earlier `pragma.test` table-info/index-info/table-valued PRAGMA rows, `pragma4.test` default-comment/table-valued join rows, PRAGMA table-valued schema behavior accepted in batch76, or source-neutral app-WAL cleanup. The new block focuses on upstream `schema.test` prepared-statement invalidation and ATTACH/DETACH schema invalidation.
- Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteAttachedSchemaCatalog`, `SQLiteSchemaRecord`, PRAGMA schema catalog execution, schema DDL current-source planning, schema cache snapshots, and ATTACH/DETACH SQL execution.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 26071 assertions, 0 failures
```
