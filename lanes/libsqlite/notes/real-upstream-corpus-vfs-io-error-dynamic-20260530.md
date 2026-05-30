# Real Upstream Corpus VFS IOERR Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T185043Z-0`

Base accepted HEAD: `0eff666a68d9fc5c2de0693a82870643615fd7c5`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr5.test`

Ported behavior:

- `ioerr.test` scenarios `ioerr-1` through `ioerr-16` covering rollback transactions, VACUUM, overflow record headers, multi-file commits, hot-journal rollback, statement playback, incremental-vacuum, pointer-map write failure, and index/delete commit paths.
- `ioerr5.test` scenario `ioerr5-1` covering pager error-state memory reclaim where dirty pages must not be spilled while the database image remains stable.
- Dynamic operation matrix: `read`, `write`, `sync`, `truncate`, `delete`, `access`, `open`, and `close`.
- Dynamic failpoint matrix: failpoints `1..8`, including upstream excluded failpoints where the fixture expects the injected probe to be ignored.

Focused count:

- `1027` distinct TestRunner PASS cases.
- `20049` behavior assertions.

Non-overlap:

- This does not repeat the accepted `io.test` atomic/default-page-size VFS corpus in `SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`.
- This does not add metadata-only admission rows or fabricated `.test` names. Every scenario cites real upstream `ioerr.test` or `ioerr5.test` names.

Dependency closure:

- No new support component is needed. The bounded native PHP support is an added `SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome()` classifier used by focused tests to model pager/VFS recovery decisions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTransactionSequencePlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php` passed: `1 test files, 20049 assertions, 0 failures`.
