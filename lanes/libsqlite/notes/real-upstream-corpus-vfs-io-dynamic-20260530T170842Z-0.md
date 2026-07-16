# Real upstream corpus VFS IO dynamic

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T170842Z-0`

Accepted base: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - `io-2.4.1`: a second connection sees the old committed rows while an atomic-write transaction is open.
  - `io-2.4.2`: atomic-write transaction does not create a rollback journal file.
  - `io-2.4.3`: after commit, the second connection sees the pending rows.

## Ported behavior

Extended `SQLiteVfsIoDynamicPlan` with `atomicTransactionVisibility()`, a generic VFS I/O behavior helper for the upstream `io-2.4.*` atomic-write visibility contract. It records whether atomic device flags suppress rollback-journal creation, whether pre-commit readers keep the old snapshot, whether pending rows become visible only after commit, and which database sync path is used.

Extended `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` from `12` to `15` focused PASS cases and from `973` to `1738` assertions. The new `+765` assertions are non-overlapping with the earlier appendvfs/io/nolock/filectrl matrix because they specifically cover the reader-visibility and rollback-journal absence behavior from `io.test` `io-2.4.1` through `io-2.4.3`.

Expected dashboard movement: `+3` focused PHP TestRunner PASS lines for this existing test file; no mapped denominator change is claimed.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 1738 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses the existing bounded VFS capability and IO traffic helpers.
