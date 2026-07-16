# real-upstream-corpus-select-core-dynamic-20260531T092250Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported scenarios: `e_select-1.8`, `e_select-1.9`, `e_select-1-10`, `e_select-1-11`, and `e_select-1.12`.

## Behavior

- `SQLiteSelectSql` now rejects explicit `ON` or `USING` constraints on `NATURAL` joins before table-alias parsing can absorb the clause.
- `NATURAL CROSS JOIN` is accepted for the upstream no-common-column case and is evaluated through the same predicate path as an implicit `USING` join.
- `SQLiteSelectProjection` now expands unqualified `SELECT *` over `USING` and `NATURAL` joins with SQLite's single coalesced output column, omitting the duplicate right-side join column while preserving non-join column order.
- The directly coupled `SQLiteRealUpstreamESelect2SubqueryUsingAffinityDynamicTest.php` expectations were updated to the corrected coalesced wildcard shape.

## Non-Overlap

- Avoided the ready scalar-subquery handoff for `select1.test` sections `select1-18.3`, `select1-18.4`, and `select1-20.10`.
- Avoided the ready `e_select.test` ORDER BY collation handoff for `e_select-8.8.1`, `8.8.2`, `8.9.1`, `8.9.2`, `8.10.1`, `8.10.2`, `8.10.3`, and `8.12.1`.
- This slice owns only SELECT core LEFT/USING/NATURAL join semantics from the upstream sections above.

## Evidence

- Red-first probe: before the fix, `SELECT * FROM t1 NATURAL LEFT JOIN t2 USING (a)` and `... ON (...)` returned rows instead of raising the upstream `NATURAL join may not have an ON or USING clause` error.
- Existing coupled guard before expectation correction exposed the wildcard `USING` shape mismatch in `SQLiteRealUpstreamESelect2SubqueryUsingAffinityDynamicTest.php`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php` -> no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteSelectProjection.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNaturalLeftJoin20260531T092250ZTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelect2SubqueryUsingAffinityDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNaturalLeftJoin20260531T092250ZTest.php` -> `1 test files, 57007 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2SubqueryUsingAffinityDynamicTest.php` -> `1 test files, 18755 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectESelect2JoinSemanticsDynamicTest.php` -> `1 test files, 13004 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDUsingLeftJoinDynamicTest.php` -> `1 test files, 7505 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicJoinWhere20260531Test.php` -> `1 test files, 6410 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> passed.

## Countability

- New PASS growth: +1001 distinct TestRunner PASS cases from `SQLiteRealUpstreamSelectCoreDynamicNaturalLeftJoin20260531T092250ZTest.php`.
- New behavior assertions in the added file: 57007.
- `lane-status.json` `phpPass`: `2835919 -> 2836920`.
- Mapped coverage remains `1589 / 1589`; this is behavior/PASS growth over an already mapped upstream file.

## Dependency Closure

No new support component is required. The slice reuses existing `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteSelectProjection` execution paths.

## Root Harness

Not run - isolated micro-slice.
