# Row-Value UPDATE/DELETE RETURNING Savepoint Current-Source Next205

## Behavior

This slice adds a bounded next205 planner for row-value `UPDATE` / `DELETE`
`RETURNING` batches inside a savepoint where `RELEASE` promotes the savepoint
image into the parent current source. The follow-up statement reads that
released current source, so `UPDATE OR REPLACE` conflict deletes and row-value
`DELETE RETURNING` effects are visible to the next row-value `UPDATE` and
`DELETE`.

## Evidence

- Focused test:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext205Test.php`
  passed with `1 test files, 64 assertions, 0 failures` and 64 PASS lines.
- Application smoke:
  `php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next205.php`
  passed and reported `nextReadReleasedCurrentSource: true`, `savepointReturned:
  3`, `nextReturned: 4`, and final copied `wp_options` ids `[1,3,5,6]`.
- Syntax:
  `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext205Plan.php`
  `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext205Test.php`
  `php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next205.php`

## Non-Overlap

This avoids next203 `OR IGNORE` / `OR REPLACE` only flow, next178 `OR ROLLBACK`
transaction rollback, next172 `ROLLBACK TO` yielded-stream suppression, trigger
RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters. It only covers
savepoint `RELEASE` admission into the parent current source for row-value
`UPDATE` / `DELETE RETURNING`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`SQLiteUpdateDeleteReturningSql` executor, row-value predicate/assignment
support, conflict handling, and lane-local savepoint current-source images.
