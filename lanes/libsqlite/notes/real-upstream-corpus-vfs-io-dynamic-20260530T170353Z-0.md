# Real upstream corpus VFS IO dynamic follow-up

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T170353Z-0`

Accepted base: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/nolock.test`
  - `nolock-3.1` and `nolock-3.11`: `SQLITE_IOCAP_IMMUTABLE` suppresses `xLock`, `xUnlock`, `xCheckReservedLock`, and `xAccess` calls even without a URI `immutable=1` parameter.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filectrl.test`
  - `filectrl-1.1` through `filectrl-1.6`: file-control probing and generated `etilqs_` temp filename behavior for main, temp, errno, lockproxy, and tempfilename handles.

## Ported behavior

- Extended `SQLiteVfsCapabilityPlan` with the upstream immutable device-characteristic bit.
- Extended `SQLiteVfsIoDynamicPlan::nolockProbe()` so VFS device characteristics can suppress lock/access calls in the same way as upstream `SQLITE_IOCAP_IMMUTABLE`.
- Added focused real-corpus assertions for immutable-device nolock behavior, rejected unsupported VFS device flags, file-control name hints feeding generated temp filenames, and mixed read-only/result controls in a SQL file-control sequence.

The focused VFS IO dynamic test grew from `12` to `16` TestRunner PASS cases and from `973` to `1081` assertions. This follow-up is non-overlapping with accepted file-writer, lock-state, lock-byte, sync-plan, rollback-journal apply, and file-control persistence sidecar clusters; it stays on upstream `nolock.test` device-characteristic behavior and `filectrl.test` file-control/tempfilename behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 1081 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses existing native PHP VFS/open/file-control primitives.
