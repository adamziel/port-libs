# B-tree pointer-map vacuum current-next64

This slice extends `SQLiteOverflowVacuumTruncatePlan` with a current/next
pointer-map transition view for overflow pages that are first released to the
auto-vacuum freelist and then partially or fully removed by tail truncation.

The new behavior distinguishes:

- released overflow pages that survive in the next database image as
  `free-page` pointer-map entries;
- released overflow pages whose pointer-map entries are current-only evidence
  because the physical pages are truncated out of the next image;
- the final boundary pointer-map row for the surviving tail page.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumCurrentNext64Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 278 assertions, 0 failures
```

The focused run emitted 57 PASS lines.

Application smoke:

```text
$ php lanes/libsqlite/examples/application-btree-pointermap-vacuum-current-next64.php
{
    "scenario": "copied wp_options overflow delete plus incremental vacuum pointer-map current/next64",
    "released_overflow_pages": [306, 307, 308, 309, 310],
    "surviving_freed_pointer_map_pages": [306, 307],
    "truncated_freed_pointer_map_pages": [308, 309, 310],
    "final_database_page_count": 307,
    "pointer_map_vacuum_transitions": "..."
}
```

Non-overlap: this avoids accepted overflow freelist release, bulk overflow
freeblock materialization, pointer-map truncate-vacuum current-next54, page
move/root collapse, PRAGMA integrity pointer-map pagination, VFS/WAL/SELECT/
JSON/encoding clusters, and release-runner evidence. The new surface is the
current-to-next pointer-map boundary after incremental vacuum decides which
already-freed overflow pages survive as freelist pages and which are removed
from the database image.

Dependency closure: no new support component is needed; this reuses the
existing native PHP SQLite database image, freelist, pointer-map, and overflow
vacuum primitives.
