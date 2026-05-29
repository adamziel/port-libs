# VFS Temp URI File-Control Regression Current Source Next139

- Slice: `vfs-temp-uri-filecontrol-regression-current-source-next139`
- Behavior: extends `SQLiteVfsLockByteUriShmCurrentSourceNext` with `currentSourceNext139()` for temporary VFS URI handles.
- Upstream behavior cluster: temp database handles are private current sources; URI file-controls read from the opening URI; writable file-controls on temp handles stay handle-local and do not leak into the persistent database owner controls.
- WordPress path: import scratch/temp databases can carry URI metadata such as sorter role, checkpoint intent, and busy timeout while the live copied database still persists WAL-related controls through normal owner state.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempUriFileControlRegressionCurrentSourceNext139Test.php`
  - Result: `1 test files, 53 assertions, 0 failures`
  - Regression pair: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenShmFileControlUriCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteVfsTempUriFileControlRegressionCurrentSourceNext139Test.php`
  - Result: `2 test files, 116 assertions, 0 failures`
  - Example smoke: `php lanes/libsqlite/examples/wordpress-vfs-temp-uri-filecontrol-regression-current-source-next139.php --self-test`
  - Result: `wordpress-vfs-temp-uri-filecontrol-regression-current-source-next139 self-test passed`
- Non-overlap: avoids accepted VFS file writer, locked writer, rollback-journal apply, VFS sync/apply, process lock, SHM lock range, URI SHM file-control current-source next128, and batch136 non-VFS behavior surfaces. This patch is a narrower temp-handle URI/file-control regression on the existing VFS current-source helper.
- Dependency closure: no new support component is needed; this reuses the existing bounded `SQLiteFileUri`, lock-byte range, and VFS current-source helper infrastructure.
