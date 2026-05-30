# B-tree vacuum pointer-map freeblock current-source next205

Status: focused PHP behavior growth for `btree-vacuum-pointermap-freeblock-current-source-next205`.

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext205Plan`, a next-writer freeblock handoff layer after the accepted next203 current-source cursor admission. It proves that pointer-map dependency pages are handed off before reusable leaf freeblock and overflow payload pages, that the leaf freeblock receipt survives from the secure-delete current source, and that fenced tail pages remain blocked from the next writer.

Application smoke: `application-btree-vacuum-pointermap-freeblock-current-source-next205.php` models deleting an overflow-backed copied `wp_options` transient before writing a replacement cache value. The handoff exposes pointer-map pages `2`/`105`, reusable leaf page `3`, and overflow payload pages `106`/`107`/`108`, while pages `109`/`110` stay fenced.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext205Test.php`
  - `1 test files, 691 assertions, 0 failures`
  - `115` PASS lines.
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext205Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext205Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next205.php`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next205.php`
  - `application-btree-vacuum-pointermap-freeblock-current-source-next205 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +115` from `98594` to `98709` in this isolated lane status. Mapped upstream coverage remains unchanged at `620 / 1589`; this is current-source B-tree handoff behavior over already mapped vacuum/pointer-map/freeblock inventory rather than a fresh upstream manifest row.

Non-overlap: this adds the next-writer freeblock handoff after next203 cursor admission. It does not repeat next203 cursor admission, next196 source-next tokens, next172 materialized images, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, freelist trunk pointer-map reuse, or accepted batch109-113 B-tree surfaces.

Dependency closure: no new support component is needed. The slice reuses existing native PHP B-tree page images, pointer-map dependency metadata, source-next cursor tokens, secure-delete leaf freeblock receipts, and fenced-tail metadata.

Next task: continue with non-overlapping B-tree delete/rebalance/freeblock application or move to another under-owned libsqlite bucket after this handoff is integrated.
