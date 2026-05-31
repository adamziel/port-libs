# real-upstream-corpus-upsert-returning-dynamic-20260531T054950Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-1100`: an `INTEGER PRIMARY KEY ON CONFLICT REPLACE` row is not replaced when the statement targets a different unique constraint with `ON CONFLICT (...) DO NOTHING`.
  - `upsert1-1300`: duplicate SELECT input rows that drive UPSERT updates preserve the correct old row image for trigger-style old/new comparisons, including large text payloads.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-5.3` through `returning1-5.5`: `RETURNING` rows expose the final changed row image after UPDATE/INSERT behavior around generated/virtual-column-style tables.

## PHP Coverage

- Added `lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicCurrentCorpusTest.php`.
- The test seeds 125 generic application variants with 8 focused TestRunner cases per variant.
- Focused delta: `1000` PASS cases / assertions.
- The batch uses generic `app_*`, `key_name`, `value_text`, and `load_policy` data only.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicCurrentCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicCurrentCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicCurrentCorpusTest.php`
  - `1 test files, 1000 assertions, 0 failures`

## Non-Overlap

- This does not repeat the accepted `upsert4`, `upsert5`, `upsert2` repeated-source yield matrix, `returning1-17` duplicate RETURNING row stream, trigger/FK RETURNING, row-value UPDATE/DELETE RETURNING, or recursive view UPSERT/RETURNING slices.
- It owns `upsert1-1100`, `upsert1-1300`, and `returning1-5` source-image behavior through the existing native dynamic UPSERT/RETURNING executor.

## Dependency Closure

- No new support component is needed. The slice reuses existing native PHP UPSERT conflict handling, statement-order RETURNING projection, and row-image tracking helpers.
