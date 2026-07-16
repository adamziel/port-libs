# B-tree Page Move Overflow Current Next17

This slice covers a table-leaf auto-vacuum page move gap: when the last table
leaf page is relocated into a freelist slot, first-overflow pointer-map entries
owned by cells on the moved page must point at the new page number. The existing
index-leaf move path already rewrote first-overflow parents; the table-leaf path
now mirrors that behavior while leaving next overflow pages parented to their
previous overflow page.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePageMoveTableOverflowCurrentNext17Test.php`
- `php lanes/libsqlite/examples/application-autovacuum-table-overflow-page-move.php`
- `php -l lanes/libsqlite/src/SQLiteBTreePageMovePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreePageMoveTableOverflowCurrentNext17Test.php`
- `php -l lanes/libsqlite/examples/application-autovacuum-table-overflow-page-move.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the existing
bounded SQLiteDatabase page reader, table leaf cell overflow reader, freelist
allocation plan, and pointer-map update machinery.

Non-overlap: this is not the accepted table/index page relocation summary, root
collapse, overflow freelist release, or bulk overflow freeblock materialization.
It narrows the page-move behavior to table-leaf cells with overflow payloads and
the first-overflow pointer-map parent rewrite needed after the page number
changes.
