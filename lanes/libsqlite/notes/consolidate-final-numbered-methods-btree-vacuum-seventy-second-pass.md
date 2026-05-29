# B-tree vacuum numbered method consolidation seventy-second pass

Consolidated the final B-tree vacuum pointer-map/freeblock replay callers that still referenced removed numbered tail entry points.

- Replaced the direct numbered tail callers with the canonical `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCurrentSourceFreelistHandoffFromDeleteResult()` entry point.
- Renamed the direct test/example files to stable descriptive names.
- Preserved the sixteen replay scenarios as data-driven freelist handoff cases.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffReplayTest.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-freelist-handoff-replay.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffReplayTest.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-freelist-handoff-replay.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing native B-tree vacuum pointer-map/freeblock freelist handoff implementation.
