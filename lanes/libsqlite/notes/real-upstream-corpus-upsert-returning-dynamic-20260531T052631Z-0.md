# real-upstream-corpus-upsert-returning-dynamic-20260531T052631Z-0

Ported a focused dynamic corpus from real upstream SQLite UPSERT/RETURNING behavior:

- `upsert2.test` `upsert2-200` and `upsert2-201`: repeated input rows conflict with the same unique key and later rows see the current row image from earlier updates in the same statement.
- `returning1.test` `returning1-4.5`: RETURNING emits changed rows in statement order and omits rows skipped by a failed conflict-arm `WHERE` predicate.

The PHP corpus uses 1000 seeded cases through `SQLiteUpsertReturningDynamicCorpusPlan::upsert2RepeatedConflictReturningDynamicCases()`. Each case exercises insert, update, repeated update, failed conflict-arm predicate, returning stream order, change counts, and conflict target matching.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicCorpusPlan.php`:
  no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRepeatedConflictDynamicTest.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRepeatedConflictDynamicTest.php`:
  `1 test files, 6000 assertions, 0 failures` with 5000 focused PASS cases.
- `git diff --check -- lanes/libsqlite`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`:
  not run because `SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded UPSERT conflict-arm and RETURNING stream helpers.

Non-overlap:

- Avoids accepted UPSERT secondary conflict, excluded-alias, target-first, partial-index, broad upsert5 priority, trigger, savepoint, and row-value returning clusters. This slice owns the repeated-conflict current-row-image stream from `upsert2.test` plus RETURNING stream ordering.
