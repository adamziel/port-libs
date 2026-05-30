# WAL reader pin current-next69

Status: focused PHP behavior growth for named WAL read-mark slot current/next visibility.

This slice adds `SQLiteWalAppendPlan::readerSlotPinAppendCurrentNext()`. Unlike the older checkpoint-pinned-frame helper, this method preserves an explicit active reader slot, including slot `0` database-only readers, while a later writer appends a committed WAL transaction and a next reader advances to the new committed frame horizon.

Focused evidence:

```sh
php -l lanes/libsqlite/src/SQLiteWalAppendPlan.php
php -l lanes/libsqlite/tests/SQLiteWalReaderPinCurrentNext69Test.php
php -l lanes/libsqlite/examples/application-wal-reader-pin-current-next69.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinCurrentNext69Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinAppendCurrentNext67Test.php lanes/libsqlite/tests/SQLiteWalReaderPinAppendCurrentNext66Test.php
php lanes/libsqlite/examples/application-wal-reader-pin-current-next69.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `SQLiteWalReaderPinCurrentNext69Test.php` passed with `1 test files, 59 assertions, 0 failures`.

Dashboard delta: `phpPass` moves from `25580` to `25639` for the 59 verified PASS lines. Mapped upstream coverage remains `463 / 1589`; this is a focused runtime behavior slice, not a newly mapped upstream inventory denominator unit.

Non-overlap: avoids accepted WAL reader-pin append current-next66/67 inferred checkpoint-pin behavior, WAL checkpoint reader-pin restart, WAL reader release/checkpoint current-next, WAL byte truncation, VFS savepoint/rollback/sync/locked-writer clusters, rollback-journal commit/apply, JSON table source/cursor/constraint work, B-tree page/freelist/overflow clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new behavior is explicit current-reader slot preservation, especially database-only slot `0`, across a subsequent committed WAL append.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL append, checksum-chain, read-mark, checkpoint, and reader snapshot primitives.

Next task: continue with broader WAL pager/VFS transaction application or another non-overlapping reader/checkpoint durability edge; avoid another reader-pin wrapper unless it applies a distinct pager state transition.
