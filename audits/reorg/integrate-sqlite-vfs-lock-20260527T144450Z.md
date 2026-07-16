# Integrate SQLite VFS Lock/Open File-Control Deps

- Handoff DB id: 298
- Slice: vfs-lock-open-file-control-deps
- Source commit: b8121dc68b5aa5de4d96a518de457220e34f4720
- Patch: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-vfs-lock-20260527T124524Z.patch`
- Patch sha256: `f3f1377d23564f214a3ba8fbb067c7306126b22c1e1a43dcda1179a990d86c82`

## Apply

Plain `git apply --check` from a clean source-baseline worktree reported
expected drift in `lanes/libsqlite/lane-status.json` and
`lanes/libsqlite/notes/root-harness.md`. `git apply --3way` applied the
behavioral files cleanly and left only those two metadata files conflicted.
Resolution preserved newer accepted libsqlite status/root-harness content and
layered in this handoff's VFS open file-control application note.

## Focused Verification

- `sha256sum` matched the ready marker hash.
- Metadata and worker log showed focused evidence for the new
  `SQLiteVfsOpenFileControl` helper, focused assertions, and the WordPress
  smoke example.
- `php -l lanes/libsqlite/examples/wordpress-vfs-open-file-control-apply.php`
  passed.
- `php -l lanes/libsqlite/src/SQLiteVfsOpenFileControl.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` passed:
  `1 test files, 9300 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-vfs-open-file-control-apply.php`
  passed and reported `fileControlApplied = 4`, `bytesPreallocated = 16896`,
  `targetSize = 20480`, and `finalSize = 20480`.
- `git diff --check -- lanes/libsqlite audits/reorg` passed.

## Root Verification

Exact process-table check before root:

```sh
ps -eo pid=,args= | awk '$0 ~ /php tools\/run-tests\.php$/ {print}'
```

Result: no existing no-argument root harness process was running.

Serialized root command:

```sh
TMPDIR="$PWD/.tmp-root" php tools/run-tests.php
```

Result: `215 test files, 34580 assertions, 0 failures`.

## Dependency Decision

Accepted. The slice adds lane-local VFS open file-control application for
`SQLITE_FCNTL_SIZE_HINT` against native PHP file handles and reuses existing
open-plan, file-control state, and file-handle primitives. No new shared
dependency component is required.

## Files Changed

- `lanes/libsqlite/src/SQLiteVfsOpenFileControl.php`
- `lanes/libsqlite/tests/SQLiteHeaderTest.php`
- `lanes/libsqlite/examples/wordpress-vfs-open-file-control-apply.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/root-harness.md`
- `audits/reorg/integrate-sqlite-vfs-lock-20260527T144450Z.md`
