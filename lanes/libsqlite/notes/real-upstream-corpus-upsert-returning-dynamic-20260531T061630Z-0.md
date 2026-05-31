# real-upstream-corpus-upsert-returning-dynamic-20260531T061630Z-0

Base accepted HEAD: `2139c8ce030e83a04c23079c17d6da80f20ffd83`.

Added `SQLiteUpstreamUpsertReturningDynamicRealCorpusNextTest.php` with 2,000 focused PASS cases over real upstream SQLite behavior:

- `upsert2.test` `upsert2-200`: repeated `INSERT ... SELECT ... ON CONFLICT DO UPDATE` source rows chain through the current row image, skip failed `WHERE` updates, and return only inserts/successful updates.
- `upsert1.test` `upsert1-320`: partial unique-index conflict handling treats rows outside the partial predicate as distinct while updating active rows.
- `upsert4.test` section `1.*`: `ON CONFLICT DO NOTHING` suppresses RETURNING rows for rowid and secondary unique conflicts but returns fresh inserted rows.
- `returning1.test` `4.5`: wildcard-style RETURNING row images preserve statement order, defaults, old images for updates, and final cardinality.

The batch is non-overlapping with accepted UPSERT target-alias/excluded-alias work and avoids the parked excluded-alias regression path. It exercises generic application settings rows only; no new support component is needed because it reuses the existing native `SQLiteUpsertReturningDynamicPlan` executor.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicRealCorpusNextTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicRealCorpusNextTest.php` passed: `1 test files, 2000 assertions, 0 failures`.

Expected movement: `+2000` focused libsqlite PASS lines; mapped denominator remains `1589 / 1589`.
