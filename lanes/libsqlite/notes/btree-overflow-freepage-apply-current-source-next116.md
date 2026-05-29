# B-tree overflow freepage apply current-source next116

This slice adds `SQLiteBTreeOverflowFreepageApplyCurrentSourceNextPlan` for
the table-leaf path where obsolete overflow page numbers are derived from the
current database image before freepage application. The plan deletes the target
rowids in order, follows each overflow next-page chain from the live cell,
applies the existing freeblock/empty-leaf freepage plans, materializes secure
delete clearing, updates the freelist trunk/leaf state, and rewrites
auto-vacuum pointer-map entries to `free-page`.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreepageApplyCurrentSourceNext116Test.php
```

Result:

```text
1 test files, 41 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-btree-overflow-freepage-apply-current-source-next116.php
```

The smoke reports copied `wp_options` transient cleanup diagnostics including
derived overflow chains `[[5, 6], [7, 8, 9, 10]]`, freeblock then empty-leaf
freepage transitions, final freelist count `7`, and pointer-map free-page
state for the emptied leaf and overflow tail.

Non-overlap:

This avoids accepted bulk overflow-backed delete/freeblock materialization,
overflow freelist release wrappers, overflow rebalance freepage current-source
next94/99 explicit-page plans, incremental-vacuum reuse, root collapse, page
relocation, index-interior merge, VFS/WAL transaction application, JSON table,
encoding, and SELECT planner clusters. The new behavior is applying freepage
side effects from current-source overflow next pointers instead of trusting an
externally supplied obsolete-overflow list.

Dependency closure:

No new support component is needed. The patch composes existing native PHP
SQLite database image, table leaf, overflow chain, freelist, pointer-map, and
secure-delete primitives.
