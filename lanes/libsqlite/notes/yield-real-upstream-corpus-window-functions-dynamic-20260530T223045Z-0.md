# real-upstream-corpus-window-functions-dynamic-20260530T223045Z-0

Accepted base: `9f789d799d368a95f9314c9ed366646dd5d17143`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` sections `1.1-1.19`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` sections `2.1-2.3.3`

Patch contents:

- Added `SQLiteRealUpstreamWindow4ValueOffsetDynamicTest.php`.
- Ports real upstream window4 value/offset behavior for `ntile()`, `lead()`, `lag()`, and row-specific `nth_value()` into focused PHP TestRunner cases.
- Adds 1,015 distinct PASS cases and 5,015 behavior assertions.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow4ValueOffsetDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow4ValueOffsetDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow4ValueOffsetDynamicTest.php`
  - `1 test files, 5015 assertions, 0 failures`

Non-overlap:

- Avoids the accepted/current `window9.test` collation and FILTER frame dynamic corpus.
- Avoids accepted JSON, pager/WAL, B-tree, VFS, PRAGMA, trigger/FK, UPSERT/RETURNING, and SELECT core corpus surfaces.
- This batch is only upstream `window4.test` value/offset rows, not metadata-only admission and not fabricated script ids.

Dependency closure:

- No new support component needed. The focused tests reuse existing `SQLiteWindowFunction` helpers for value offsets, bucket assignment, and row-specific `nth_value()` frame behavior.

Next candidate:

- A follow-up window batch can target upstream `window4.test` sections `3.x-4.x` aggregate/value frame combinations, keeping it separate from this value/offset corpus and from the earlier window9 collation/FILTER batch.
