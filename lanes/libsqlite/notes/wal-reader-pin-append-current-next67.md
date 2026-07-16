# WAL reader pin append current-next67

Status: focused PHP behavior growth for WAL reader read-mark handoff while a writer appends committed frames.

## Scope

- Adds `SQLiteWalAppendPlan::readerPinAppendCurrentNext()` to compose existing WAL append/checksum planning with WAL-index read-mark current/next state.
- Covers a current reader pinned to an older frame, assignment of the next reader to the appended committed frame, checkpoint reset blocking while the old pin remains, release-readmark planning, uncommitted WAL tails, full read-mark tables with no free next-reader slot, and invalid input guards.
- Adds `application-wal-reader-pin-append-current-next67.php` for copied `wp_options` import behavior where a current reader keeps an older `siteurl` snapshot while the next reader sees an appended import transaction.

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinAppendCurrentNext67Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-wal-reader-pin-append-current-next67.php --self-test
{
    "scenario": "application-wal-reader-pin-append-current-next67",
    "status": "current-reader-pinned-next-reader-advanced",
    "current_reader_frame": 2,
    "next_reader_frame": 5,
    "next_reader_slot": 2,
    "checkpoint_before_release_busy": true,
    "current_pin_blocks_checkpoint": true,
    "dependency": true
}
```

## Dashboard delta

- `phpPass`: `25055 -> 25118` from the 63 verified focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is lane-local WAL read-mark/append behavior, not a newly mapped upstream inventory unit.

## Non-overlap

This avoids accepted WAL checkpoint transactions, WAL byte truncation, VFS savepoint rollback, WAL reader-pin current-next64 restart/release handoff, WAL restart/savepoint checkpoint, WAL checksum recovery apply, rollback-journal commit/apply, VFS writer/sync/lock clusters, SELECT SQL text/subquery/group/order clusters, JSON table source/cursor/constraint clusters, B-tree page/freelist/overflow clusters, and Unicode GLOB behavior. The new surface is append-time read-mark ownership for current versus next WAL readers.

## Dependency closure

No new support component is needed. The patch reuses lane-local WAL frame checksum chaining, append transaction planning, read-mark planning, durable checkpoint result planning, and VFS file-write coordination markers.

## Next

Continue with broader pager/VFS transaction application or durable checkpoint/reset application that writes through real file handles; avoid another WAL reader-pin wrapper unless it changes applied pager bytes or removes a root/upstream runner blocker.
