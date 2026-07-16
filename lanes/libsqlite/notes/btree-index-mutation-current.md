# B-tree Index Mutation Current

Slice: `libsqlite-btree-index-mutation-current`

Implemented `SQLiteBTreeIndexMutationCurrent`, a stable unsuffixed helper for
current index leaf mutation. It deletes a stale index record, verifies the
deleted local cell is exposed as reusable freeblock space, inserts the
replacement record through `SQLiteIndexLeafPage::insertCellByRecordValuesReusingFreeblock()`,
and reports the before/delete/insert page-header evidence.

Focused behavior:

- Single wp_options-style index replacement from `active_plugins` to `autoload`.
- Batch replacement that applies two current index mutations on the same page.
- Overflow-backed deleted record reporting obsolete overflow page numbers while
  inserting a short local replacement into the freed page space.
- Negative guards for duplicate replacement records, missing delete keys, empty
  batches, and malformed batch rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexMutationCurrentTest.php`
  expected result: `1 test files, 43 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-btree-index-mutation-current.php --self-test`
  expected result: JSON `status` of `ok`.
- `php -l` for changed PHP files.
- `git diff --check -- lanes/libsqlite`.

Dependency closure: no new support component is needed; this reuses
`SQLiteIndexLeafPage`, `SQLiteIndexCell`, `SQLiteOverflowPage`, `SQLiteRecord`,
and `SQLiteBTreePageHeader`.

Non-overlap: this does not introduce numbered production classes and does not
repeat accepted page relocation, root collapse, overflow freelist release
planning, bulk overflow freeblock materialization, VFS writer, WAL checkpoint,
or suite-only evidence work.
