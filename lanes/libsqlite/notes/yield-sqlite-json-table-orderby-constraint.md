# JSON Table ORDER BY Constraint Current Source Next120

## Behavior

- Added JSON table planner recognition that an `ORDER BY` term is already
  satisfied when a usable visible constraint fixes that ordered column to a
  single value (`=`, `IS`, `IS NULL`, `IS NOT DISTINCT FROM`, singleton `IN`,
  or equal-bound `BETWEEN`).
- Preserved the existing natural `id ASC` / rowid order handling, and added
  current-source next120 coverage metadata that explains whether each order
  term was consumed by natural rowid order, a constant visible constraint, or
  left for a sorter.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableOrderByConstraintTest.php`
- Result: `1 test files, 58 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-orderby-constraint.php`

## Non-Overlap

This avoids the accepted JSON table hidden/visible constraint extraction,
parser-level JSON table `FROM` source wiring, JSON table cursor behavior, and
batch109-113 cost/order cluster by adding the narrower ORDER BY-constant
constraint consumption rule on top of current-source planning.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
JSON table planner, JSON tree/every row materializer, and Application example
smoke path.
