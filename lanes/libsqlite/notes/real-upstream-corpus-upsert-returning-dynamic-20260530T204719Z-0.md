# real-upstream-corpus-upsert-returning-dynamic-20260530T204719Z-0

Implemented a bounded UPSERT/RETURNING behavior slice from the hydrated SQLite upstream corpus.

- Upstream source files: `returning1.test` sections 17.1 and 17.2, `upsert2.test` section 200, and `upsert1.test` section 1200.
- Port behavior: `SQLiteUpsertReturningSql` now accepts `ON CONFLICT DO UPDATE ... RETURNING` without an explicit conflict target when the caller provides unique-constraint metadata, matching the upstream no-target UPSERT form used by `returning1.test` 17.*.
- Focused coverage: `SQLiteRealUpstreamUpsertReturningNoTargetDynamicTest.php` adds 1007 distinct TestRunner PASS cases and 3016 assertions, including 1000 generated row-stream cases over the same no-target UPSERT/RETURNING conflict family.
- Non-overlap: this does not repeat accepted `upsert2` broad/static rows, accepted `returning1` correlated delete rows, trigger/FK RETURNING rows, or WordPress-shaped staging examples. The new tests use generic `app_counter` and `app_metric` table names only.
- Dependency closure: no new support component is needed. The existing row-array UPSERT executor and unique-constraint metadata path are reused.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNoTargetDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNoTargetDynamicTest.php`
