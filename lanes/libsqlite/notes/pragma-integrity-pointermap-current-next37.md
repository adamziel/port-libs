# PRAGMA Integrity Pointer-Map Current/Next 37

This slice extends native PHP `PRAGMA integrity_check` for auto-vacuum
pointer-map entry validation before the deep B-tree walk. It catches malformed
pointer-map entry types and impossible parent fields that can otherwise hide
behind zeroed pages or later page parsing.

Behavior covered:

- Unknown pointer-map entry type bytes are surfaced as integrity errors before
  the deep B-tree walk.
- `root-page` and `free-page` entries must have parent `0`.
- `btree-page`, `first-overflow-page`, and `overflow-page` entries reject
  parent `0` and parent page numbers beyond the database image.
- `PRAGMA quick_check` remains shallow and does not run the pointer-map parent
  validation.
- `PRAGMA integrity_check(N)` still honors the requested error limit.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapCurrentNext37Test.php
Focused test run: 1 selected test files (root lock skipped)
45 PASS lines
1 test files, 45 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-integrity-pointermap-current-next37.php
```

The smoke reports copied `wp_options`-style auto-vacuum page images where
`integrity_check` catches malformed pointer-map tags and parent fields while
`quick_check` stays shallow.

Non-overlap:

This does not repeat accepted PRAGMA quick/integrity header/freelist count
checks, pointer-map free-page reachability, B-tree page-move/root-collapse,
overflow freelist release, freeblock rebalance, VFS writer/sync/rollback,
JSON table source/cursor/constraint, Unicode GLOB, or SELECT SQL text
clusters. The new surface is malformed pointer-map entry type and parent
field validation for current auto-vacuum images.

Dependency closure:

No new support component is needed. The implementation reuses existing native
PHP SQLite database, pointer-map, freelist, and PRAGMA integrity primitives.
