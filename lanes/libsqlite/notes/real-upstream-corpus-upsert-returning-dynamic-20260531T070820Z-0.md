# real-upstream-corpus-upsert-returning-dynamic-20260531T070820Z-0

Implemented a bounded real-upstream UPSERT/RETURNING boolean-literal slice.

- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test` `returning1-19.1` for `RETURNING FALSE` / `RETURNING TRUE` acceptance.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test` `upsert2-320/321` for `DO UPDATE ... WHERE false` suppressing changed rows and RETURNING output.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test` for dynamic ON CONFLICT arm dispatch over repeated input rows.
- Behavior change: `SQLiteUpsertReturningSql` now treats SQL `TRUE`/`FALSE` as SQLite numeric truth values in UPSERT `DO UPDATE WHERE` predicates, literal evaluation, and RETURNING projections with optional aliases.
- Focused test growth: added `SQLiteRealUpstreamCorpusUpsertReturningDynamicBooleanLiteralTest.php`, 100 dynamic seeds x 4 boolean variants, checked against an in-memory SQLite PDO oracle for RETURNING streams, final table images, `changes()`, update/skip counts, and numeric truth aliases.
- Verification:
  - `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicBooleanLiteralTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicBooleanLiteralTest.php` -> `1 test files, 3601 assertions, 0 failures`
- Dependency closure: no new support component needed; this reuses the existing UPSERT RETURNING SQL executor plus local PDO SQLite only as the focused oracle inside tests.
- Non-overlap: does not repeat accepted excluded-alias, upsert5 priority matrix, partial-predicate, trigger histogram, SELECT-input, repeated-conflict, or redundant-conflict corpus slices; this owns boolean literal parsing/evaluation in UPSERT RETURNING SQL text.
