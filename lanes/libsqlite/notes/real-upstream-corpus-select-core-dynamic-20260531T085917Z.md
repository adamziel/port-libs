# Real Upstream SELECT Core Dynamic Sort/Collation

- Micro-slice: `real-upstream-corpus-select-core-dynamic-20260531T085917Z-0`
- Accepted base: `9986ffeeb381ed3e9dc9166d1668e256084ca733`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Upstream scenarios: `e_select-8.8.1`, `e_select-8.8.2`, `e_select-8.9.1`, `e_select-8.9.2`, `e_select-8.10.1`, `e_select-8.10.2`, `e_select-8.10.3`, `e_select-8.12.1`

## Behavior

This handoff ports SELECT-core dynamic sorting behavior from real upstream
`e_select.test`: mixed SQL storage-class ordering, explicit `ORDER BY COLLATE`
override, result-expression `COLLATE` inheritance through `ORDER BY 1` and
result aliases, plain source-column `ORDER BY` collation non-inheritance, and
binary fallback for arbitrary `ORDER BY` expressions.

The red-first focused run exposed a real port gap: `SELECT label COLLATE nocase
AS sorted_label FROM labels ORDER BY sorted_label` sorted by BINARY output order
instead of inheriting the result-expression `NOCASE` collation. The source fix
teaches `SQLiteSelectSql` to inherit result-term collation for `ORDER BY`
ordinals and explicit result aliases while leaving plain source-column
`ORDER BY label` behavior unchanged.

The related SELECT collation-family check also exposed a compound SELECT
collation keying bug: first-arm `COLLATE` metadata was keyed by the source
column name even when compound rows were renamed to the projected result
column. `compoundSelectCollations()` now records the collation under the actual
compound projection column, preserving upstream `selectE.test` `EXCEPT`
comparison semantics.

## Non-Overlap

This slice avoids accepted expression-`ORDER BY` shape coverage, compound
collation batches, JSON table SELECT-source/cursor behavior, VFS/WAL/pager
storage clusters, and source-neutral cleanup work. It is limited to simple
SELECT-core `ORDER BY` comparator behavior and result-expression collation
inheritance from upstream `e_select.test` sections 8.8 through 8.12.

## Evidence

- Red-first before fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSortCollation20260531T085917ZTest.php`
  failed with `1 test files, 17013 assertions, 1000 failures`; every dynamic
  seed failed the `e_select-8.10.3 result alias collation inheritance` case.
- After fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSortCollation20260531T085917ZTest.php`
  passed with `1 test files, 33014 assertions, 0 failures`.
- Related SELECT collation family:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicWhereOrderTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicCompoundCollationTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCompoundCollationDynamicTest.php`
  passed with `3 test files, 10930 assertions, 0 failures`.
- No-domain guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `1 test files, 3 assertions, 0 failures`.
- PHP syntax:
  `php -l lanes/libsqlite/src/SQLiteSelectSql.php` and
  `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSortCollation20260531T085917ZTest.php`
  both passed.
- Status JSON validation:
  `php -r '$path="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " valid JSON\n";'`
  passed.
- Whitespace check:
  `git diff --check -- lanes/libsqlite` passed.
- PASS movement: `+1002` focused TestRunner PASS lines.
- Mapped coverage: unchanged at `1589 / 1589`; this is behavior/PASS growth
  over an already mapped upstream SELECT file.

## Dependency Closure

No new support component is needed. The slice reuses existing
`SQLiteSelectSql`, `SQLiteSelectResult` mixed-value sorting, `SQLiteBlobValue`,
and the hydrated upstream SQLite SELECT corpus.

Root harness status: not run - isolated micro-slice.
