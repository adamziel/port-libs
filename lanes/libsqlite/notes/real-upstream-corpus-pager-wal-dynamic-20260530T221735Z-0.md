# real-upstream-corpus-pager-wal-dynamic-20260530T221735Z-0

Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
  - `wal-3.*`: transaction rollback in WAL mode.
  - `wal-4.*`: savepoint and statement rollback in WAL mode.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - hot rollback-journal recovery restores original database page images.

## PHP coverage added

- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRollbackSavepointDynamicTest.php`
- 500 distinct rollback-journal recovery cases over page size, sector size,
  initial database size, restored page numbers, hot-journal detection,
  recovery plan application, final truncation, and delete-journal action.
- 500 distinct WAL savepoint rollback cases over page size, byte order,
  page-count, frame/page choices, retained/discarded frame sets, byte
  truncation offsets, reparse after truncation, committed transaction boundary,
  and restart checkpoint result.
- 1 metadata case records exact hydrated upstream file/section ownership.

Focused result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRollbackSavepointDynamicTest.php
1 test files, 12001 assertions, 0 failures
1001 PASS lines
```

Expected accepted selected movement: `912041 -> 913042` PHP PASS lines if
integrated without overlap. Mapped denominator remains `1589 / 1589`.

## Non-overlap

This slice does not repeat the existing pager/WAL dynamic transaction batch,
follow-up WAL checksum/checkpoint matrix, WAL restart/noop, WAL mode/persist,
VFS rollback apply, WAL byte truncation, checkpoint transaction, or savepoint
image rollback clusters. It owns the narrower real upstream rollback and
savepoint sections named above.

## Dependency closure

No new support component is needed. The test reuses existing native PHP
`SQLiteRollbackJournal`, `SQLiteWal`, and `SQLiteSavepointStack` behavior.

## Root status

Root harness not run - isolated micro-slice.
