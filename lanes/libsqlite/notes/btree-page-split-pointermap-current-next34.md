# B-tree page split pointer-map current-next34

2026-05-27 isolated slice `yield-sqlite-btree-page-split-pointermap-current-next34`.

## Behavior

- Added focused coverage for auto-vacuum table-root page splits in copied `wp_options` images.
- The tests rebuild the current/next database image after `planOptionRowReplace()` splits a root leaf into an interior root plus two table-leaf children.
- The assertions verify the root remains a `root-page` pointer-map entry, both split children become `btree-page` entries owned by the current root page, rowid order is preserved, and replacement payload/autoload state survives.
- Added a Application smoke example that prints the split root, child page numbers, and pointer-map entries without requiring `ext/sqlite`.

## Verification

Local run in this worktree:

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePageSplitPointerMapCurrentNext34Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS splits auto-vacuum table root and rewrites pointer-map child ownership
PASS preserves split pointer-map current/next pages for variant 00
...
PASS preserves split pointer-map current/next pages for variant 48

1 test files, 1450 assertions, 0 failures

$ php lanes/libsqlite/examples/application-btree-page-split-pointermap-current-next34.php
{
    "scenario": "application-btree-page-split-pointermap-current-next34",
    "root": {
        "page": 3,
        "pageType": "table-interior",
        "leftChild": 4,
        "rightMostPointer": 5
    }
}
```

Syntax and whitespace checks were also run before handoff; see final response for exact command results.

## Status Delta

- Expected `phpPass`: `11752 -> 11802` from 50 new focused PASS cases in the new test file.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a new upstream inventory mapping.

## Non-overlap

This slice avoids accepted B-tree page relocation, root collapse, table/index interior merge, overflow freelist release, bulk overflow freeblock materialization, VFS/WAL/SELECT/JSON/encoding clusters, and batch23-28 surfaces. It covers the narrower root-page split pointer-map current/next image behavior for auto-vacuum table-root splits.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native PHP `SQLiteDatabase`, `SQLitePointerMapEntry`, and table B-tree page assembly helpers.
