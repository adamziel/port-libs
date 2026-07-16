# VFS Temp Lock File-Control Current Source Next102

- Added `SQLiteVfsTempLockingFileControlCurrentSourcePlan::currentSourceNext102()` for temp statement-journal/current-source handles that track xFileControl write generations and `data_version` reads.
- The new path requires a reserved/pending/exclusive temp lock before write-like controls (`chunk_size`, `size_hint`, `persist_wal`, `reserve_bytes`, and `powersafe_overwrite`) can mutate state.
- Sibling temp handles opened on the same current source report stale `data_version` after another locked handle changes file-control state; close/reopen starts at the latest persisted generation.
- DELETEONCLOSE and memory temp-store handles clear or avoid persistent control/generation state, matching SQLite temp-file lifecycle expectations for copied Application import statement journals.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteVfsTempLockingFileControlCurrentSourcePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsTempLockingFileControlCurrentSourcePlan.php

php -l lanes/libsqlite/tests/SQLiteVfsTempLockFileControlCurrentSourceNext102Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVfsTempLockFileControlCurrentSourceNext102Test.php

php -l lanes/libsqlite/examples/application-vfs-temp-lock-filecontrol-current-source-next102.php
No syntax errors detected in lanes/libsqlite/examples/application-vfs-temp-lock-filecontrol-current-source-next102.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempLockFileControlCurrentSourceNext102Test.php
1 test files, 50 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempLockingFileControlCurrentSourceNext83Test.php lanes/libsqlite/tests/SQLiteVfsTempLockFileControlCurrentSourceNext102Test.php
2 test files, 113 assertions, 0 failures

php lanes/libsqlite/examples/application-vfs-temp-lock-filecontrol-current-source-next102.php --self-test
application-vfs-temp-lock-filecontrol-current-source-next102 self-test passed
```

Non-overlap:

This avoids accepted temp file-control persistence next76, temp current-source routing next83, main database current-source generation next99, VFS lock-state/process-lock/locked-writer/sync/rollback-journal clusters, WAL checkpoint/savepoint byte paths, B-tree page/freelist/overflow clusters, JSON table source/cursor/constraint work, SQL SELECT text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is specifically temp-file current-source `data_version` freshness across locked xFileControl writes, delete-on-close cleanup, and memory temp-store non-persistence.

Dependency closure:

No new support component is required. This reuses the existing bounded temp-file lifecycle and VFS file-control/lock state helpers, adding only native PHP temp current-source generation tracking for future pager/VFS consumers.
