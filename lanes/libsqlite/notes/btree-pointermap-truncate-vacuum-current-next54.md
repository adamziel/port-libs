# B-tree Pointer-map Truncate Vacuum Current Next54

This slice extends `SQLiteOverflowVacuumTruncatePlan` with explicit current and
next database images. The current image is the post-delete overflow-release
state with auto-vacuum pointer-map entries rewritten to `free-page`; the next
image is the post-VACUUM tail truncation state with those tail freelist pages
removed from the database image.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteOverflowVacuumTruncatePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreePointerMapTruncateVacuumCurrentNext54Test.php`
- `php -l lanes/libsqlite/examples/application-btree-pointermap-truncate-vacuum-current-next54.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapTruncateVacuumCurrentNext54Test.php`
  - `1 test files, 65 assertions, 0 failures`
  - 59 focused `PASS` lines
- `php lanes/libsqlite/examples/application-btree-pointermap-truncate-vacuum-current-next54.php`

Application smoke:

The smoke reports a copied `wp_options` autoload/index overflow tail cleanup:
current state keeps pages 306-310 on the freelist with pointer-map entries
rewritten to free-page, and next state truncates the database to page 305 with
an empty freelist.

Non-overlap:

This does not repeat accepted table/index page relocation, root-collapse,
overflow freelist release alone, bulk overflow freeblocks, PRAGMA pointer-map
integrity diagnostics, or the older tail-truncation helper. The new surface is
the composed current/next database boundary after pointer-map overflow release
and VACUUM truncation, including the post-release pointer-map entries and the
post-truncation database image.

Dependency closure:

No new support component is needed. The slice reuses native PHP SQLite page
images, freelist trunk parsing, auto-vacuum pointer-map mutation, and existing
tail-truncation planning.
