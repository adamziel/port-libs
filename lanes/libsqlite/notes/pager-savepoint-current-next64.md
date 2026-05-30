# Pager savepoint current next64

Status: focused PHP behavior growth for `ROLLBACK TO` keeping the named savepoint current and making the next WAL frame start at the retained prefix plus one.

## Behavior

- Added `SQLiteSavepointStack::rollbackToCurrentAndRecordNextWalFrame64()`.
- Covers nested Application-style import savepoints where discarded WAL frames from the target and child savepoints are removed, the target savepoint remains open/current, and a retry write records the next WAL frame at `rollback_to_frame + 1`.
- Preserves retained outer transaction WAL/page state, rejects invalid pages/missing savepoints, supports case-insensitive savepoint lookup, and exposes dependency tags for pager current/next handling.
- Added `application-pager-savepoint-current-next64.php` as a copied `wp_options` plugin-settings import smoke.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext64Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS pager savepoint current next64 plugin plan savepoint
PASS pager savepoint current next64 plugin plan found index
PASS pager savepoint current next64 plugin plan rollback frame
PASS pager savepoint current next64 plugin plan next wal frame reuses current prefix
PASS pager savepoint current next64 plugin plan next page
PASS pager savepoint current next64 plugin plan commit marker
PASS pager savepoint current next64 plugin plan discarded frame indexes
PASS pager savepoint current next64 plugin plan discarded frame pages
PASS pager savepoint current next64 plugin plan discarded commit markers
PASS pager savepoint current next64 plugin plan discarded frame names
PASS pager savepoint current next64 plugin plan discarded page numbers
PASS pager savepoint current next64 plugin plan retained frame names
PASS pager savepoint current next64 plugin plan retained wal before next
PASS pager savepoint current next64 plugin plan pending pages before next
PASS pager savepoint current next64 plugin plan pending wal after next
PASS pager savepoint current next64 plugin plan pending pages after next
PASS pager savepoint current next64 plugin plan keeps current savepoint active
PASS pager savepoint current next64 plugin plan keeps transaction active
PASS pager savepoint current next64 plugin plan dependency keeps savepoint marker
PASS pager savepoint current next64 plugin plan dependency keeps pager marker
PASS pager savepoint current next64 plugin stack names after next write
PASS pager savepoint current next64 plugin stack wal state transaction frames
PASS pager savepoint current next64 plugin stack wal state savepoint frames
PASS pager savepoint current next64 plugin stack savepoint wal start unchanged
PASS pager savepoint current next64 plugin stack page state transaction pages
PASS pager savepoint current next64 plugin stack page state current savepoint page
PASS pager savepoint current next64 plugin stack release after next merges new page
PASS pager savepoint current next64 plugin stack commit after next includes rewritten page
PASS pager savepoint current next64 plugin stack can append following frame
PASS pager savepoint current next64 plugin stack rejects old discarded frame after next
PASS pager savepoint current next64 case insensitive plan keeps input savepoint spelling
PASS pager savepoint current next64 case insensitive plan found target
PASS pager savepoint current next64 case insensitive plan next frame
PASS pager savepoint current next64 case insensitive plan current active
PASS pager savepoint current next64 single option plan found leaf savepoint
PASS pager savepoint current next64 single option plan rollback frame
PASS pager savepoint current next64 single option plan next frame
PASS pager savepoint current next64 single option plan discarded indexes
PASS pager savepoint current next64 single option plan discarded pages
PASS pager savepoint current next64 single option plan retained frames
PASS pager savepoint current next64 single option plan retained wal before next
PASS pager savepoint current next64 single option plan pages before next
PASS pager savepoint current next64 single option plan pending wal after next
PASS pager savepoint current next64 single option plan pending pages after next
PASS pager savepoint current next64 single option plan current active
PASS pager savepoint current next64 transaction savepoint rollback starts next after zero frame
PASS pager savepoint current next64 transaction savepoint rollback keeps only rewritten frame
PASS pager savepoint current next64 transaction savepoint rollback keeps transaction alive
PASS pager savepoint current next64 transaction savepoint rollback can commit replacement
PASS pager savepoint current next64 missing savepoint rejected
PASS pager savepoint current next64 zero next page rejected
PASS pager savepoint current next64 negative next page rejected

1 test files, 52 assertions, 0 failures
```

## Dashboard delta

- `phpPass`: `23341 -> 23393` (+52 focused PASS lines).
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged at `463 / 1589`; this slice adds focused PHP behavior coverage and does not claim a new upstream inventory unit.

## Non-overlap

This avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, release-then-rollback checkpoint visibility, WAL checkpoint transactions, rollback-journal commit/apply, super-journal commit, VFS writer/sync/lock clusters, B-tree overflow/freelist/page-move clusters, JSON table planner/cursor/source clusters, SELECT SQL text/subquery/group/order clusters, Unicode GLOB, and status-only release-runner evidence. The new surface is the pager current/next edge after `ROLLBACK TO`: the target savepoint stays open and the next WAL write reuses `rollback_to_frame + 1` rather than the discarded historical tail.

## Dependency closure

No new support component is needed. The slice reuses lane-local `SQLiteSavepointStack` pager/WAL bookkeeping and adds one bounded current/next transition helper for future pager/VFS transaction application.
