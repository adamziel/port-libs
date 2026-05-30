# B-tree Interior Redistribute Current Next70

Adds `SQLiteBTreeInteriorRedistributionCurrentNextPlan`, a bounded current/next
application summary for table and index interior sibling redistribution. It
wraps the existing page-image apply primitive and records current vs next page
hash transitions, parent divider replacement, moved child pages, and
auto-vacuum pointer-map parent transitions.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeInteriorRedistributeCurrentNext70Test.php
```

Result: `1 test files, 51 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-interior-redistribute-current-next70.php
```

The smoke reports copied `wp_options` option-name index interior redistribution
from the current image to the next image, including the parent divider update
and child page 12 moving from sibling page 8 to page 7 in pointer-map state.

Non-overlap: this avoids accepted B-tree interior redistribution diagnostics,
current-next32 pointer-map apply assertions, page move/root-collapse/overflow
freelist release, pointer-map vacuum, bulk overflow freeblocks, WAL/VFS,
JSONB, LIKE, and SELECT SQL clusters. The new surface is the current/next
transition record over the already computed redistributed interior page images.

Dependency closure: no new support component is needed. The slice reuses the
native SQLite database page reader, interior page assemblers, record codec, and
auto-vacuum pointer-map helpers already present in the libsqlite lane.
