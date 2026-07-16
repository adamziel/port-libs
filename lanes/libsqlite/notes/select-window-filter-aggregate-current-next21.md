### 2026-05-27 SELECT window FILTER aggregate current-next21

Scope:

- Adds bounded parser/executor support for `count()`, `sum()`, and
  `group_concat()` window aggregate `FILTER (WHERE ...)` clauses when an
  explicit `OVER (... ROWS|RANGE|GROUPS BETWEEN CURRENT ROW AND N FOLLOWING)`
  frame is present.
- The executor evaluates the filter predicate against each ordered partition
  row before applying the existing aggregate frame reducer, preserving
  `count(*)`, NULL-skipping aggregate values, `EXCLUDE`, partitioning,
  `CASE` predicates, and ROWS/RANGE/GROUPS current-to-following frames.
- Application smoke:
  `lanes/libsqlite/examples/application-select-window-filter-aggregate-current-next21.php`
  summarizes copied `wp_options` rows with filtered current/next autoload
  windows without requiring ext/sqlite.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectWindowFilterAggregateCurrentNext21Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php lanes/libsqlite/tests/SQLiteSelectWindowFilterAggregateCurrentNext21Test.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 108 assertions, 0 failures
```

Status delta:

- `phpPass`: `7262 -> 7318` in this isolated worktree, matching the 56 new
  focused PASS lines from the new test file.
- `UPSTREAM_TEST_MANIFEST.json`: adds
  `focusedSelectWindowFilterAggregateCurrentNext21` as one mapped focused
  behavior row; no broad upstream runner was launched.

Non-overlap:

- Avoids accepted SELECT SQL ranking/window text, JSON aggregate window state,
  grouped SELECT/GROUP BY text, scalar operators, subquery execution, expression
  ORDER BY, JSON table cursor/source/constraint work, VFS writer/sync/lock,
  rollback/WAL byte truncation, and B-tree page-move/freelist clusters.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectPredicate`, and
  `SQLiteWindowFunction` components.
