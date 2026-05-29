# btree delete overflow vacuum pointer-map current-source next119

- Implemented `SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan`, a current-source wrapper over native overflow freelist release plus incremental-vacuum truncation.
- Focused behavior: a copied `wp_options` table delete and option-name index delete release obsolete overflow chains, rewrite auto-vacuum pointer-map entries to `free-page`, then truncate only tail freed overflow pages while preserving lower freed pages behind a live page.
- Added `SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNext119Test.php`: `1 test files / 159 assertions / 0 failures`.
- Added WordPress smoke `wordpress-btree-delete-overflow-vacuum-pointermap-current-source-next119.php`.
- Non-overlap: avoids accepted bulk overflow freeblock materialization, overflow freelist release, overflow vacuum truncate next92, root collapse, page move, index-interior merge, and freelist trunk pointer-map reuse. This slice names the delete-result-to-vacuum pointer-map transition rows for current-source next119.
- Dependency closure: no new support component needed; reuses existing native `SQLiteOverflowVacuumTruncatePlan`, `SQLiteOverflowFreelistReleasePlan`, `SQLiteFreelistFreePlan`, and auto-vacuum pointer-map support.
