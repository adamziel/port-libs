# Real Upstream UPSERT RETURNING Dynamic WHERE

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T004502Z-0`

Base accepted HEAD: `93b324b07783b617d6c0938ad7bcd94b70aaa32e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
- Sections: `upsert2-100`, `upsert2-200`, and `upsert2-320`

Behavior added:

- `SQLiteUpsertReturningDynamicPlan` now supports callable `DO UPDATE SET`
  expressions so dynamic plans can model `b=excluded.b` and `c=c+1` using the
  current conflicting row image.
- The same helper now supports a dynamic `DO UPDATE WHERE` callback. When the
  predicate is false, the conflicted source row is skipped, `changes` does not
  increase, and no `RETURNING` row is emitted.
- Added focused real-upstream tests for VALUES and SELECT-source UPSERT rows,
  repeated conflicts that update the same target row more than once, skipped
  lower excluded values, and failed update-`WHERE` behavior.

Non-overlap:

- This does not repeat the accepted UPSERT/RETURNING SELECT-source batch from
  `ba91cc49a`; it extends the generic dynamic helper with callable assignment
  and update-`WHERE` semantics from `upsert2.test`.
- It does not add WordPress/domain-specific names or APIs.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicWhereTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicWhereTest.php`
  - `1 test files, 1121 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicTest.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicRealCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicWhereTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 2726 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses existing PHP row-array UPSERT
  helpers and the hydrated upstream SQLite `.test` corpus as source truth.
