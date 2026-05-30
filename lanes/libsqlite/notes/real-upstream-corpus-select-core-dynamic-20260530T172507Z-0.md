# real-upstream-corpus-select-core-dynamic-20260530T172507Z-0

Base accepted HEAD: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test`

Behavior added:

- `select2.test` `select2-1.1`: nested/re-entrant row selection slices over the bounded `tbl1` fixture.
- `select2.test` `select2-2.2`, `select2-3.1`, `select2-3.2`: bounded count predicates plus commuted/direct equality row selection over `tbl2`.
- `select2.test` `select2-4.1` through `select2-4.5`: scalar `max()`/`min()` predicates and truthy/negated truthy cross-join predicates.
- `select7.test` `select7-7.2`: computed `CASE` projection alias used as a `GROUP BY` term with aggregate `count(*)`.

Implementation note:

- `SQLiteSelectSql::groupBy()` now resolves an unqualified `GROUP BY` term that matches a SELECT-list alias to that term's source expression, which matches SQLite's computed alias grouping behavior without changing qualified column handling.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php`
- Result: `1 test files, 1533 assertions, 0 failures`
- New focused PASS cases in this patch: `+48`
- Focused behavior assertion volume after this patch: `1533` assertions in the SELECT core dynamic file.

Non-overlap:

- This does not repeat accepted grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, WAL/VFS/B-tree clusters, or prior select1/select3/select5/select6 core dynamic coverage.
- It extends the existing SELECT core dynamic corpus with select2/select7 upstream behavior and fixes a shared executor gap exposed by `select7-7.2`.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local `SQLiteSelectSql`, `SQLiteSelectExpression`, grouped aggregate, and SELECT predicate support.
