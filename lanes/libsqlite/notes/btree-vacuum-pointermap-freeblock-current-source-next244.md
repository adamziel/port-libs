# btree vacuum pointer-map freeblock current-source next244

Slice: `btree-vacuum-pointermap-freeblock-current-source-next244`.

Implemented `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Plan`, an additive publish-order validator over the accepted current-source freelist cursor. The behavior models the point where a copied `wp_options` delete makes the current-source freelist cursor visible to a following WordPress option rewrite:

- pointer-map pages are published as fences before payload pages are reusable;
- duplicate pointer-map page replays keep their generation count;
- payload rows keep the admitted freeblock receipt;
- tail-truncated overflow pages remain excluded from the published current source.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Plan.php
php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next244.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Test.php
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next244.php
git diff --check -- lanes/libsqlite
```

Expected focused delta: the new test file contributes 126 focused PASS lines and 1350 assertions. `lane-status.json` `phpPass` is updated from `122940` to `123066`. Mapped upstream coverage is unchanged at `647 / 1589`; this is current-source PHP behavior over an already mapped B-tree vacuum/freeblock family rather than a newly hydrated upstream inventory row.

Non-overlap: avoids accepted cursor validation, next238 freelist-link admission, next235 checkpoint admission, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, freelist trunk pointer-map reuse, and accepted batch210 cursor behavior. The surface is the current-source publish-order fence after cursor visibility.

Dependency closure: no new support component is needed. This slice reuses the native B-tree page, pointer-map, overflow, freeblock, and current-source cursor primitives already present in the libsqlite lane.
