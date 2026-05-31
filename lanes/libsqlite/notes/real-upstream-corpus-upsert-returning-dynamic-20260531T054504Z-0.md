# real-upstream-corpus-upsert-returning-dynamic-20260531T054504Z-0

## Scope

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`.
- Ported sections:
  - `upsert4.test-9.0`: creates an `AFTER INSERT` trigger whose body uses UPSERT into a histogram table.
  - `upsert4.test-9.1`: inserts repeated source rows and verifies final histogram counts.
- Added behavior:
  - `SQLiteUpsertReturningDynamicCorpusPlan::upsert4TriggerHistogramReturningDynamicCases()`.
  - `SQLiteRealUpstreamUpsertReturningTriggerHistogramDynamicTest.php`.

## Focused Growth

- `1000` seeded real-upstream variants.
- `4004` focused TestRunner PASS cases.
- `7005` focused assertions.
- This is non-overlapping with accepted UPSERT dynamic slices that cover `upsert2` WHERE gates, `upsert3` literal excluded-table behavior, `upsert4` target/alias/partial-index behavior, `upsert5` arm priority, trigger old-value behavior, and RETURNING correlated subqueries. This slice owns trigger-body UPSERT histogram execution from `upsert4.test-9`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicCorpusPlan.php`
  - passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerHistogramDynamicTest.php`
  - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerHistogramDynamicTest.php`
  - `1 test files, 7005 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRepeatedConflictDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerHistogramDynamicTest.php`
  - `2 test files, 13005 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  - passed.
- `SQLiteNoWordPressSpecificApiTest.php`
  - not present in this worktree.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP corpus-plan pattern and adds a bounded trigger-body UPSERT histogram model for real upstream `upsert4.test` behavior.
