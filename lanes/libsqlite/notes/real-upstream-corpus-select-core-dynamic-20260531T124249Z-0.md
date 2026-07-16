# real-upstream-corpus-select-core-dynamic-20260531T124249Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test`
- Source sections: `EVIDENCE-OF: R-28760-53843`, `$tn.28a`, `$tn.28b`, `$tn.28c`, and `$tn.28d`.
- Ported SQL cluster: `SELECT * FROM t3 NATURAL LEFT JOIN t2 NATURAL JOIN t1` and `SELECT * FROM t3 NATURAL LEFT JOIN (t2 NATURAL JOIN t1)`.

## Behavior

- `SQLiteSelectSql` now preserves result-column metadata for parenthesized join groups even when the group produces zero rows.
- LEFT and FULL JOIN null-extension now uses that table-reference column metadata instead of only columns observed from rows.
- NATURAL and USING join predicates can resolve join columns from metadata, so an empty right-side join group still participates with SQLite's known output shape.

## Red-First Evidence

- Before the source change, `SELECT b, e, d, c FROM t3 NATURAL LEFT JOIN (t2 NATURAL JOIN t1) ORDER BY b` threw `SQLite LEFT JOIN needs right-side result columns for NULL extension` when `t2 NATURAL JOIN t1` was empty.
- The focused test keeps that empty right-side join-group scenario in every dynamic case and verifies the preserved left rows are NULL-extended for the right-side `d` and `c` columns.

## Non-Overlap

- Avoids accepted e_select core join semantics, e_select2 collation and USING affinity slices, selectD parenthesized join coverage, SELECT subquery coverage, grouped SELECT text, expression ORDER BY, JSON table source/cursor coverage, and storage/VFS clusters.
- This slice owns only the e_select2 join-associativity shape issue for parenthesized right-side NATURAL join groups.

## Evidence

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinAssociativityDynamic20260531T124249ZTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinAssociativityDynamic20260531T124249ZTest.php` -> `1 test files, 17010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNaturalLeftJoin20260531T092250ZTest.php` -> `1 test files, 57007 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2SubqueryUsingAffinityDynamicTest.php` -> `1 test files, 18755 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinCollationDynamicTest.php` -> `1 test files, 22006 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> passed.

## Countability

- New PASS growth: +1002 distinct TestRunner PASS cases from `SQLiteRealUpstreamESelect2JoinAssociativityDynamic20260531T124249ZTest.php`.
- New behavior assertions in the added file: 17010.
- `lane-status.json` `phpPass`: `2918190 -> 2919192`.
- Mapped coverage remains `1589 / 1589`; this is behavior/PASS growth over an already mapped upstream file.

## Dependency Closure

No new support component is required. The slice reuses existing `SQLiteSelectSql` row-array execution and the hydrated upstream SQLite `e_select2.test` source truth.

## Root Harness

Not run - isolated micro-slice.
