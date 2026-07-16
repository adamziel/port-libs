# VDBE Numeric CAST Overflow Edge Next13

## Behavior

- Added focused SQLite VDBE-style numeric CAST overflow handling for parser-level SELECT expression evaluation.
- `CAST(text AS INTEGER)` now clamps integer prefixes outside signed int64 to `PHP_INT_MAX` / `PHP_INT_MIN`, including BLOB-backed text and decimal/exponent tails where SQLite integer casting stops at the integer prefix.
- `CAST(text AS NUMERIC)` keeps in-range integer prefixes as INTEGER and promotes out-of-range integer prefixes to REAL.
- SELECT predicate and ORDER BY numeric comparisons now distinguish positive overflow REAL values from the signed int64 maximum INTEGER boundary.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeNumericCastOverflowCorpusTest.php`
  - `1 test files, 32 assertions, 0 failures`
  - `25` PASS lines, counted as `phpPass +25`.
- `php lanes/libsqlite/examples/application-select-sql-cast-overflow.php --self-test`
  - passed.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.

## Non-Overlap

This slice avoids the accepted SELECT subquery, GROUP BY, expression ORDER BY, JSON table cursor/source/constraint, VFS writer/lock/sync, WAL checkpoint/savepoint, B-tree page move/root collapse/overflow freelist, Unicode GLOB, and rollback-journal commit clusters. It narrows the older scalar cast-affinity coverage to int64 overflow boundaries and NUMERIC REAL promotion.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP SELECT expression, predicate, and result-ordering components.
