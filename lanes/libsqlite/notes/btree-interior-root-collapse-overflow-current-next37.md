# B-tree interior root-collapse overflow current-next37

This slice makes `SQLiteBTreeRootCollapsePlan` overflow-aware when an empty
interior root copies its only child page into the root. Index interior cells can
carry overflow payloads; after the copy, SQLite auto-vacuum pointer-map entries
for the copied cell's first overflow page must point at the root page, while
subsequent overflow pages still point to the previous overflow page.

Focused coverage:

- `SQLiteBTreeRootCollapseOverflowCurrentNext37Test.php` adds 63 PASS cases for
  an index-interior child with a copied overflow-backed separator cell.
- The Application smoke
  `application-btree-interior-root-collapse-overflow-current-next37.php` reports
  copied `wp_options` autoload-index root-collapse diagnostics, including child
  pointer reparenting, first-overflow owner rewrite, unchanged next-overflow
  links, freelist release of the obsolete child, and root payload round-trip.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeRootCollapseOverflowCurrentNext37Test.php
```

Result: `1 test files, 63 assertions, 0 failures`.

Non-overlap: this does not repeat accepted table/index page relocation, index
interior merge, root-collapse child/grandchild-only pointer-map coverage,
overflow freelist release, bulk overflow freeblocks, VFS writer/sync/lock,
WAL savepoint/checkpoint, JSON table, SELECT SQL, or Unicode GLOB work. The new
surface is the current/next pointer-map transition for a copied index-interior
cell's first overflow page during root collapse.

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP database page reader, overflow payload reader, index-cell
parser, pointer-map writer, and freelist free plan.
