# real-upstream-corpus-trigger-fkey-dynamic-20260530T192406Z-0

Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported section: `fkey2-5.*`, specifically the incremental BLOB API rule that writable handles may not open a foreign-key column while foreign keys are enabled, but readonly handles are allowed.

Implemented behavior:

- `SQLiteIncrementalBlobIoPlan::open()` now accepts `foreign_key_columns`.
- Writable opens against those columns throw `RuntimeException`.
- Readonly opens against those columns still return a normal handle.
- Writable opens against non-FK blob columns remain valid.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteIncrementalBlobIoPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyBlobColumnDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyBlobColumnDynamicTest.php`
  - `2102 PASS cases / 1 test files, 2104 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteIncrementalBlobIoCorpusTest.php`
  - `1 test files, 40 assertions, 0 failures`

Non-overlap:

- Does not repeat the accepted trigger/FK action matrix, savepoint deferred chain, nocase repair, recursive cascade, view-trigger, RETURNING, or FK action corpus files.
- Uses the unported `fkey2-5.*` incremental blob/FK-column guard section.

Dependency closure:

- No new support component is required. The existing `SQLiteIncrementalBlobIoPlan` and `SQLiteBlobValue` components are reused.
