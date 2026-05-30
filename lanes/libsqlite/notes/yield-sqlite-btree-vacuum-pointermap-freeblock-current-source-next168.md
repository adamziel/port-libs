# B-tree Vacuum Pointer-Map Freeblock Current-Source Next168

## Scope

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext168Plan`.
- The slice stays inside the B-tree ownership bucket and extends the accepted
  next164 overflow-chain continuity path with a separate leaf-page guard: the
  deleted table leaf image must remain byte-stable after partial auto-vacuum
  truncates/reallocates overflow pages, and the leaf page must keep its
  auto-vacuum pointer-map root ownership.
- Application smoke models copied `wp_options` cleanup where a transient row is
  deleted while overflow pages are reused for replacement payload bytes.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext168Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 362 assertions, 0 failures
```

The focused test produced 68 PASS lines.

Example smoke:

```text
php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next168.php
```

The smoke emitted `stableLeafPages: [3]`, `leafErrors: []`, released overflow
pages `[106,107,108,109,110]`, and allocated replacement overflow pages
`[106,107,108,109]`.

## Non-Overlap

- Avoids accepted next164 overflow-chain continuity as the primary behavior;
  next168 uses it as the base and adds leaf-page byte-stability, free-space
  accounting, and root pointer-map ownership checks.
- Does not repeat accepted page relocation, root collapse, overflow freelist
  release, bulk overflow freeblocks, or freelist trunk pointer-map reuse
  surfaces.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteDatabase`, table leaf page, pointer-map, overflow, and free-space
parsing primitives.
