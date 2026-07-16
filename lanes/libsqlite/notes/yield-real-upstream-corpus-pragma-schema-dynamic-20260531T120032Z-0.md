# real-upstream-corpus-pragma-schema-dynamic-20260531T120032Z-0

Base accepted HEAD: `ab384a0d481bd4acef6592a38a3540df9d0cc3f2`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-11.1`: `PRAGMA collation_list` returns `seq` and `name` rows.
  - `pragma-11.2`: application-defined collations appear in the collation list.
- `/home/claude/port-libs/.upstream-cache/libsqlite/ext/expert/expert1.test`
  - `expert1-6.0`: `pragma_collation_list` is a queryable row source.

## Behavior Ported

- Added the missing `pragma_collation_list` virtual-table schema shape to
  `SQLitePragmaSchemaCatalog`.
- New focused coverage exercises:
  - `PRAGMA table_info(pragma_collation_list)` and
    `PRAGMA table_xinfo(pragma_collation_list)` returning visible `seq` and
    `name` columns.
  - Direct `PRAGMA collation_list` and table-valued `pragma_collation_list()`
    row parity.
  - Virtual-table SELECT ordering and filtering over built-in and dynamic
    application collations.

## Evidence

- New focused test:
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCollationVirtual20260531T120032ZTest.php`
- Focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCollationVirtual20260531T120032ZTest.php`
  -> `1 test files, 5256 assertions, 0 failures`
- Adjacent regression/API guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCollationVirtual20260531T120032ZTest.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaRuntimeListDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVirtualShape20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  -> `5 test files, 32264 assertions, 0 failures`
- Syntax:
  - `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
    -> `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCollationVirtual20260531T120032ZTest.php`
    -> `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCollationVirtual20260531T120032ZTest.php`
- Diff hygiene:
  `git diff --check -- lanes/libsqlite` -> no output.
- TestRunner PASS delta: `+1001` distinct focused PASS cases.
- Mapped denominator delta: `0`; this deepens already mapped PRAGMA/schema
  coverage.

## Non-Overlap

This slice does not repeat the accepted `trusted_schema` result-shape batch,
the prior runtime `PRAGMA collation_list` list coverage, or existing
`function_list`/`module_list`/`pragma_list` virtual schema shapes. The gap was
specific to `pragma_collation_list` table-info/xinfo metadata and SELECT row
source behavior.

## Dependency Closure

No new support component is needed. The slice reuses
`SQLitePragmaSchemaCatalog` virtual PRAGMA rowsets and existing virtual-table
SELECT execution.
