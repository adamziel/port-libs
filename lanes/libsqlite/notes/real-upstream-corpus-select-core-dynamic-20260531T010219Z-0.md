# real-upstream-corpus-select-core-dynamic-20260531T010219Z-0

Base accepted HEAD: `db598d2f37de4eb8809eabdfe8470ae863639e6e`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
- Focused upstream scenarios: `select6-9.7`, `select6-9.8`, and `select6-9.9`
  negative inner `LIMIT` subquery behavior.

Change:

- Fixed the core SELECT early-limit path so `LIMIT -1` keeps SQLite's
  no-limit semantics instead of being clamped to zero rows.
- Added `SQLiteRealUpstreamSelectCoreDynamicNegativeLimitBatch0Test.php` with
  1,000 dynamic upstream-derived `select6.test` cases plus one source-citation
  case. The cases vary source cardinality, grouping buckets, inner `OFFSET`,
  outer `LIMIT`, and outer `OFFSET`.

Non-overlap:

- This slice does not repeat accepted SELECT JOIN text, GROUP BY/HAVING text,
  expression ORDER BY, compound-collation, select2 join semantics, or selectC
  alias behavior. It owns the `select6.test` negative inner limit subquery
  regression path that was red in the current accepted file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNegativeLimitBatch0Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php`
  - Before fix: `1 test files, 8900 assertions, 3 failures`
  - After fix: `1 test files, 8910 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNegativeLimitBatch0Test.php`
  - `1 test files, 13996 assertions, 0 failures`

Dashboard expectation:

- Countable focused PASS-line growth: `+1001` from the new test file.
- Existing focused blocker removed: 3 red `select6.test` negative-limit cases
  in `SQLiteRealUpstreamSelectCoreDynamicTest.php` now pass.
- Mapped denominator movement: none; this is already within mapped SELECT
  corpus coverage.

Dependency closure:

- No new support component is needed. The fix reuses existing
  `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteSelectResult` limit/offset
  primitives.
