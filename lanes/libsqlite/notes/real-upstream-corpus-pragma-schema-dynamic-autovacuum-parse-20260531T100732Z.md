# real-upstream-corpus-pragma-schema-dynamic-autovacuum-parse-20260531T100732Z

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T100732Z-0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Ported section: `pragma-17.1.*` "Parsing of auto_vacuum settings."

## Behavior

Added a focused real-upstream corpus test for the full `auto_vacuum` parser
normalization matrix:

- numeric `0`, `1`, `2` map to `NONE`, `FULL`, and `INCREMENTAL`;
- out-of-range numeric values `3`, `-1`, `-1234`, and `1234` normalize back to
  `NONE`;
- case-insensitive `none`, `full`, and `incremental` keyword spellings produce
  the same effective PRAGMA rows as upstream;
- direct, parenthesized, and schema-qualified PRAGMA spellings preserve the
  same main-schema state and row shape.

## Count

- New focused TestRunner PASS cases: 1001
- Focused behavior assertions: 14008 from the 1000 generated parser/state cases
  plus 8 source-citation/dependency assertions
- Mapped denominator: unchanged; this is PASS growth over already hydrated
  upstream `pragma.test`.

## Non-Overlap

This avoids the accepted/current PRAGMA schema dynamic clusters for
`pragma4.test` table-valued joins, `pragma5.test` virtual-table metadata,
`pragma6.test` runtime lists, data-version, writable-schema/integrity,
cache/default-cache/cache-spill, temp_store transaction rejection, page-count
application, schema object namespaces, schema3 refresh, schema5 legacy
constraints, and schema6 equivalence. The new surface is specifically the
upstream `pragma-17.1.*` `auto_vacuum` parsing matrix over
`SQLitePragmaEncodingPageTempStoreState`.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
`SQLitePragmaEncodingPageTempStoreState` native PRAGMA state model.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicAutoVacuumParse20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicAutoVacuumParse20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicAutoVacuumParse20260531Test.php`
  - `1 test files, 14008 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: this base does not contain that path; `SQLiteNoDomainSpecificApiTest.php` is the current guard
- `git diff --check -- lanes/libsqlite`
  - passed with no output
