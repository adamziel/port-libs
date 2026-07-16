# real-upstream-corpus-select-core-dynamic-20260531T111241Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T111241Z-0`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported sections: `e_select-8.13`, `e_select-8.14`, and `e_select-8.15`.
- Behavior: compound SELECT `ORDER BY` term resolution searches result
  expressions from left to right across compound arms, rejects unmatched
  non-alias expressions, and allows separate `ORDER BY` terms to match
  expressions from different arms.

## PHP coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundOrderResolutionDynamic20260531T111241ZTest.php`.
- Focused TestRunner growth: 1,002 PASS cases.
  - 1 upstream source citation case.
  - 1,000 bounded dynamic compound `ORDER BY` arm-resolution cases.
  - 1 non-overlap and dependency-closure case.
- Focused result: `1 test files, 46009 assertions, 0 failures`.

## Non-overlap

This owns the upstream `e_select.test` compound `ORDER BY` result-expression
resolution cluster. It does not repeat accepted expression `ORDER BY`
projection/collation coverage, prior `select4`/`selectB` compound result
batches, SELECT joins/subqueries/GROUP BY text, JSON table sources/cursors/
constraints, PRAGMA, WAL, VFS, B-tree, source-neutral cleanup, or metadata-only
runner rows.

Mapped denominator coverage remains unchanged because `e_select.test` is
already represented in the hydrated upstream manifest. Expected selected
PASS-line movement is `+1002`; selected behavior assertion movement is
`+46009`.

## Dependency closure

No new support component is needed. This reuses the existing lane-local
`SQLiteSelectSql` parser/executor and compound `ORDER BY` resolution behavior
against real upstream SQLite SELECT semantics.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundOrderResolutionDynamic20260531T111241ZTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundOrderResolutionDynamic20260531T111241ZTest.php`
  passed: `1 test files, 46009 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `1 test files, 3 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  passed.
- `git diff --check -- lanes/libsqlite` passed.
- Root harness not run - isolated micro-slice.
