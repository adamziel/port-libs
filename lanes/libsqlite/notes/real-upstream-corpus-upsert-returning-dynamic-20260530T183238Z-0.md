# real-upstream-corpus-upsert-returning-dynamic-20260530T183238Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - Ported section `upsert5-3.0` through `upsert5-3.6`.
  - Scenario: redundant `ON CONFLICT` arms following `REPLACE INTO` must not corrupt the table or secondary unique indexes.

Focused coverage:

- Added `SQLiteRealUpstreamUpsertReturningDynamicRedundantConflictTest.php`.
- Adds `1001` focused TestRunner PASS cases and `6001` behavior assertions.
- Covers one-index and two-index layouts with dynamic row images:
  - primary-key replacement deletes the old row and inserts the new row;
  - RETURNING row image is the replacement row;
  - table scan and secondary-index scan views remain consistent;
  - unique-index integrity remains `ok`;
  - redundant conflict arms use the first matching arm when probed after the replacement image.

Non-overlap:

- This does not repeat accepted UPSERT priority/full-matrix/statement/correlated/schema-variant coverage.
- This specifically owns upstream `upsert5.test` section 3 redundant-conflict index-integrity behavior.
- No WordPress-specific source/API names were added.

Dependency closure:

- No new support component is needed.
- The slice reuses existing native PHP row-array UPSERT conflict-arm and RETURNING projection helpers plus lane-local test modeling for REPLACE-style primary-key replacement.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicRedundantConflictTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicRedundantConflictTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicRedundantConflictTest.php`
  - `1 test files, 6001 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: guard file is not present in this worktree

Root harness:

- Not run - isolated micro-slice.
