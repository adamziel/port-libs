# PRAGMA Integrity Deep Current Next19

This slice extends the existing native PHP `PRAGMA integrity_check` implementation beyond header/freelist/pointer-map bounds into bounded b-tree page integrity. It stays disjoint from accepted schema PRAGMA catalog, VFS writer/sync/rollback, WAL checkpoint/savepoint, JSON table, Unicode GLOB, SELECT SQL, and B-tree page relocation/root-collapse/overflow-freelist clusters.

Focused behavior:

- `PRAGMA integrity_check` now inspects present b-tree page images for valid page type, cell pointer array bounds, freeblock accounting, table/index leaf cells, table interior cells, and index cells.
- Deep overflow cells are followed through native overflow pages, with loop/trailing-page checks delegated to the existing overflow primitive.
- Auto-vacuum pointer-map entries are cross-checked for b-tree root/non-root pages and first/subsequent overflow pages.
- `PRAGMA quick_check` preserves the existing shallower behavior and still skips the deep b-tree/overflow walk.

Verification from this worktree:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityDeepCurrentNext19Test.php`: `1 test files, 31 assertions, 0 failures` with 31 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityQuickCheckCorpusTest.php lanes/libsqlite/tests/SQLitePragmaIntegrityDeepCurrentNext19Test.php`: `2 test files, 65 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pragma-integrity-quickcheck.php`: printed quick-check `ok`, limited header corruption, and deep b-tree `ok` rows for copied `wp_options`-style page images.
- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityCheck.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityDeepCurrentNext19Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-pragma-integrity-quickcheck.php`: no syntax errors.
- `git diff --check -- lanes/libsqlite`: no whitespace errors.

Dashboard delta:

- Adds 31 new focused PASS lines in `SQLitePragmaIntegrityDeepCurrentNext19Test.php`.
- `lane-status.json` `phpPass` moves from 6444 to 6475 in this isolated worktree.
- `benchmarkDenominator.mapped` is unchanged; this is a focused behavior/test-growth slice, not a new upstream inventory admission.

Dependency closure: no new support component is needed. The implementation reuses existing native PHP SQLite database/header, b-tree page header, table/index cell, freelist, overflow-page, and pointer-map primitives.
