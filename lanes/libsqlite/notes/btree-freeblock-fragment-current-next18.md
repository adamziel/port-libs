# B-tree Freeblock Current/Next Fragment Slice

Date: 2026-05-27

This slice fixes the B-tree page freeblock parser for SQLite's current/next
freeblock fragment edge. A next freeblock may start 1-3 bytes after the current
freeblock ends; those bytes are page-local fragmented free bytes, not a corrupt
overlap. The parser now rejects only overlapping next offsets, and
`SQLiteBTreePageHeader::freeblockFragmentReport()` reports the
fragment bytes accounted between current/next freeblocks.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreeblockFragmentCurrentNext18Test.php
```

Result: `1 test files, 48 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-page-freeblocks.php /tmp/libsqlite-current-next18-fragment.sqlite 2
```

Result: the smoke reported `freeblockCurrentNextFragments.status: ok`,
`current_next_fragment_bytes: 3`, and one fragment between offsets 412 and 415.

Non-overlap: this does not repeat accepted overflow freelist release, bulk
overflow freeblocks, empty-leaf release, root collapse, page relocation,
index-interior merge, VFS writer/sync/rollback apply, WAL byte truncation, JSON
table cursor/source/constraint pushdown, Unicode GLOB, SELECT SQL grouping,
subqueries, or comma LIMIT clusters. It is limited to b-tree page-local
freeblock chain parsing for valid fragmented gaps between current and next
freeblocks.

Dependency closure: no new support component is needed. The patch reuses
lane-local B-tree page header parsing and the existing Application page-freeblock
diagnostic smoke.
