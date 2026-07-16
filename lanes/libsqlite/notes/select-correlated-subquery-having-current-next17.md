# SELECT Correlated Subquery HAVING Current Next17

Status delta: added a focused parser/executor corpus for correlated subqueries
whose grouped `HAVING` predicates read the current outer row. The cases cover
scalar subqueries, `EXISTS` / `NOT EXISTS`, `IN` / `NOT IN`, joined and
left-joined outer rows, qualified outer-column references, NULL current values,
duplicate inner column names, CTE subquery sources, and final ORDER/LIMIT/OFFSET
composition.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCorrelatedSubqueryHavingCurrentNext17Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `35 PASS lines, 1 test files, 37 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-select-correlated-subquery-having-current.php`

Status counters:

- `lane-status.json` `phpPass`: `5718 -> 5753` (`+35` verified PASS lines)
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit was
  admitted in this micro-slice.

Non-overlap: this does not repeat accepted top-level `GROUP BY` / `HAVING`,
parser-level SELECT/JOIN/subquery text dispatch, JSON table source/cursor/
constraint work, VFS/WAL/B-tree clusters, expression `ORDER BY`, comma `LIMIT`,
or the next15 correlated aggregate GROUP/HAVING corpus. The added behavior is
the current outer-row value resolution inside grouped subquery `HAVING`
predicates, especially when the outer row is a joined row with qualified
columns.

Dependency closure: no new support component is needed. This reuses the
existing bounded `SQLiteSelectSql`, `SQLiteSelectQuery`,
`SQLiteSelectPredicate`, and grouped aggregate executor.
