# real-upstream-corpus-select-core-dynamic-20260601T022721Z-0

Base accepted HEAD: `aae30af0e20a252fbc6d49ffeaf4400dbc5a6747`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test`
- `e_select-2.2.1` cases 1 through 7: a table-or-subquery in `FROM` is materialized as a table for plain joins, `ON` predicates, `NATURAL JOIN`, and `NATURAL LEFT JOIN` null extension.

Implemented:

- Added `SQLiteRealUpstreamESelect2DerivedSourceDynamic20260601T022721ZTest.php`.
- Added 1000 dynamic TestRunner cases plus upstream source citation and non-overlap/dependency tests.
- Each dynamic case checks derived subquery sources for cartesian joins, `ON` predicates with the derived source on both sides, derived cross-source joins, `NATURAL JOIN` both directions, and `NATURAL LEFT JOIN` orphan-row null extension.
- Uses independent PHP oracle loops over generic `t1`, `t2`, and `t3` rows; no generated fake upstream script ids or metadata-only admission rows.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2DerivedSourceDynamic20260601T022721ZTest.php`
  - `1 test files, 29010 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelect2DerivedSourceDynamic20260601T022721ZTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

Non-overlap:

- Avoids accepted direct e_select2 join semantics, e_select2 join collation, `NATURAL LEFT JOIN` associativity, `USING` affinity cases 8 through 15, selectD parenthesized joins, SELECT subquery predicates, grouped SELECT, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows.

Dependency closure:

- No new support component needed. This reuses existing `SQLiteSelectSql` derived table, subquery source, `JOIN`, `NATURAL JOIN`, and `LEFT JOIN` row-array execution plus hydrated upstream source truth.

Expected dashboard movement:

- `phpPass`: `5399381 -> 5400383` (`+1002` focused TestRunner PASS lines).
- Mapped denominator remains `1589 / 1589`.
- `phpFail` remains `7` known broad-lane failures.
