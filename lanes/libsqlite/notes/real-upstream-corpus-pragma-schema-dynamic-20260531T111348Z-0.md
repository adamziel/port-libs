# real-upstream-corpus-pragma-schema-dynamic-20260531T111348Z-0

Base accepted HEAD: `729105b48b26aa61ef0db4b008592ded7b7410d2`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trustschema1.test`
  - `trustschema1` toggles `PRAGMA trusted_schema` through `OFF`, `ON`, and mixed-case forms while checking schema-trust behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma4-1.*` establishes the PRAGMA result-shape contract used here: query form returns one column/row and assignment form returns zero columns/rows.

## Behavior Ported

- `SQLitePragmaResultShape` now recognizes `trusted_schema` as a query-or-assignment PRAGMA.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedShape20260531T111348ZTest.php` with 1001 TestRunner PASS cases:
  - 500 variants for schema-qualified `PRAGMA trusted_schema` query and truthy RHS assignment shape/state.
  - 500 variants for false RHS forms and connection-local state independence.
  - 1 citation/parser guard against the real upstream files.

This is non-overlapping with the accepted PRAGMA virtual-table SELECT slice (`pragma_function_list`, `pragma_module_list`, `pragma_pragma_list`) and with the existing `trustschema1` runtime safety corpus. The new tests target result-shape classification and connection-local PRAGMA state.

## Verification

- Red-first focused check before the source fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedShape20260531T111348ZTest.php`
  - Result: `1 test files, 9 assertions, 1000 failures`
  - Failure: `SQLite PRAGMA result shape does not support trusted_schema`
- Focused corpus after the fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedShape20260531T111348ZTest.php`
  - Result: `1 test files, 14509 assertions, 0 failures`
- Adjacent PRAGMA regression:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedShape20260531T111348ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicBooleanState20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicResultShape20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedSchema20260531Test.php`
  - Result: `4 test files, 34949 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/libsqlite/src/SQLitePragmaResultShape.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTrustedShape20260531T111348ZTest.php`
- API guard:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`

## Dashboard Delta

- Expected selected PASS delta: `+1001`
- `phpPass`: `2893069 -> 2894070`
- `phpFail`: unchanged at `0`
- Mapped denominator coverage: unchanged at `1589 / 1589`

## Dependency Closure

No new support component is needed. This reuses existing bounded PRAGMA result-shape and connection boolean state helpers.
