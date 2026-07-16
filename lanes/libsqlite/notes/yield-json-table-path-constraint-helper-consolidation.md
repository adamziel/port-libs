# JSON Table Path Constraint Helper Consolidation

Date: 2026-05-29

Slice: `consolidate-final-numbered-methods-json-table-ninety-eighth-pass`

Change:
- Renamed the private `SQLiteJsonTablePlan` path-constraint helper group from
  numbered `...123` names to stable unsuffixed names:
  `jsonTablePathConstraintProfile()`, `jsonTablePathTape()`,
  `jsonTablePathConstraintTransitions()`, and
  `jsonTablePathConstraintReplanReasons()`.
- Preserved observable output keys and values including `next123ReplanReasons`
  and the `sqlite-json-table-path-constraint-pushdown-current-source-next123`
  dependency string.

Dependency closure:
- No new support component is needed. This is a private helper-name
  consolidation inside the existing JSON table planner implementation.

Non-overlap:
- This cleanup only touches the path-constraint private helper names and does
  not repeat JSON table visible/hidden constraint behavior, cursor/source
  wiring, malformed JSONB behavior, or rowid/generated-path cost behavior.

Verification:
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteJsonTablePathConstraintPushdownTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTablePathConstraintPushdownTest.php`
  passed: `1 test files, 57 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTable*Test.php`
  passed: `305 test files, 20187 assertions, 0 failures`.
- Exact user-named 150 suffix scan in `src`/`tests`/`examples`/`notes` passed.
- Removed helper-reference scan for
  `jsonTablePathConstraint(Profile|Tape|Transitions|ReplanReasons)123` passed.
- `git diff --check -- lanes/libsqlite` passed.
