# B-tree Delete Overflow Materialization Sequential Deletes

Adds `SQLiteBTreeDeleteOverflowPlan`, a bounded native PHP
sequential delete materializer for consecutive overflow-backed deletes on the same
table or index leaf. The current delete is applied into a database image first;
the next delete is derived from that materialized page, then its obsolete
overflow pages are connected into the updated freelist and auto-vacuum
pointer-map state.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeDeleteOverflowMaterializationTest.php`
  - `1 test files, 78 assertions, 0 failures`
- `php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/wordpress-btree-delete-overflow.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeDeleteOverflowPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeDeleteOverflowMaterializationTest.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-delete-overflow.php`
- `git diff --check -- lanes/libsqlite`

WordPress smoke:

The copied `wp_options` transient cleanup smoke reports current and next
deleted rowids, released overflow pages, freelist growth, materialized page
numbers, and the remaining leaf cell count without requiring `ext/sqlite`.

Non-overlap:

This avoids accepted bulk overflow freeblock materialization, overflow
freelist release, B-tree page relocation, root collapse, index-interior merge,
freeblock-only defragment diagnostics, pointer-map vacuum apply, and batch74
single-delete freeblock apply. The new surface is sequencing current and next
deletes through the materialized current page image so the next delete cannot
use a stale leaf image.

Dependency closure:

No new support component is needed. The slice reuses lane-local B-tree
table/index leaf deletion, page-header/freeblock parsing, record/cell codecs,
freelist planning, and auto-vacuum pointer-map update primitives.
