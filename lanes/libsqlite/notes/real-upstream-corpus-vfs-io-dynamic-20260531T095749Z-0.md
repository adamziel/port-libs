# real-upstream-corpus-vfs-io-dynamic-20260531T095749Z-0

Base accepted HEAD: `633d868181ed471ba314711c0ee3aff27a79b97e`.

Source truth: hydrated upstream SQLite checkout under
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream sections ported:

- `oserror.test` `oserror-1.1.1` and `oserror-1.1.3`: too-many-open-file
  descriptor probes may either succeed or log `open|getcwd(test.db)` diagnostics.
- `oserror.test` `oserror-1.2.1` and `oserror-1.2.2`: opening a directory path
  returns `unable to open database file` and logs `open(dir.db)`.
- `oserror.test` `oserror-1.3.1` and `oserror-1.3.2`: missing parent paths
  return `unable to open database file` and log `open(test.db)`.
- `oserror.test` `oserror-1.4.1` and `oserror-1.4.2`: restricted root paths
  return `unable to open database file` and log `open|readlink|lstat(test.db)`.
- `oserror.test` `oserror-2.1.1` through `oserror-2.1.3`: WAL sidecar unlink
  failures return `disk I/O error`, log `unlink(test.db-wal)`, and leave the
  database reusable after cleanup.

Behavior covered:

- Adds `SQLiteVfsIoDynamicPlan::osErrorLogProfile()` for upstream-shaped
  `sqlite3_log` OS-error diagnostics, syscall/path validation, VFS result-code
  mapping, and cleanup/reuse metadata.
- Adds 1,000 dynamic real-upstream cases plus citation, malformed-input, and
  pass-count guards in `SQLiteRealUpstreamCorpusVfsOsErrorLogDynamicTest.php`.
- Focused local result: `1 test files, 30008 assertions, 0 failures`.

Non-overlap:

- This does not repeat accepted VFS diskfull, syscall mapping, file writer,
  rollback-journal apply, sync apply, process-lock, lock-state, lock-byte-range,
  WAL checkpoint, WAL byte-truncation, or previous `ioerr*.test` dynamic error
  injection clusters.
- The owned behavior is the `oserror.test` sqlite3_log OS-error regex and
  result-code contract.

Dependency closure:

- No new support component is needed. The slice extends the existing bounded
  native PHP VFS/I/O dynamic planner and ports real upstream behavior into
  lane-local PHP tests.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsOsErrorLogDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsOsErrorLogDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
