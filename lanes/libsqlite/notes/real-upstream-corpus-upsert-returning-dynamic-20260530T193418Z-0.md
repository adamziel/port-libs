# real-upstream-corpus-upsert-returning-dynamic-20260530T193418Z-0

Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`.

This slice extends the existing real upstream UPSERT/RETURNING dynamic corpus
with paired multi-row statement cases derived from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  scenarios `upsert5-1.x.100` through `upsert5-1.x.505`, covering generalized
  conflict-arm priority, catch-all arms, duplicate arm ordering, and DO NOTHING
  suppression.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  scenarios `returning1-4.2`, `returning1-4.5`, and `returning1-17`, covering
  RETURNING rows emitted for mixed insert/update UPSERT statements in statement
  order.

The new paired-yield block contributes 1218 additional focused assertions in
`SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php`, bringing the focused
file to 1416 assertions. The cases are non-overlapping with the existing
single-row dynamic corpus because each test executes two UPSERT input rows in a
single statement shape and verifies final row image, RETURNING stream, selected
conflict-arm order, changes count, and skipped-row count.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php`
  -> `1 test files, 1416 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php`
  -> no syntax errors
- `git diff --check -- lanes/libsqlite`
  -> passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  -> not run; guard file is absent in this worktree

Dependency closure: no new support component is needed. The slice reuses the
existing bounded native `SQLiteUpsertDoUpdateWherePlan` conflict-arm and
RETURNING projection behavior.
