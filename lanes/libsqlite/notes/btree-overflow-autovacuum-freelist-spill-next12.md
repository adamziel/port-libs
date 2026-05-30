# B-tree Overflow Auto-vacuum Freelist Spill Next12

This slice adds focused coverage for a full-trunk freelist edge when deleting
overflow-backed copied `wp_options` table and index cells in an auto-vacuum
database.

The behavior is intentionally narrower than the accepted bulk overflow
freeblock and overflow freelist release clusters: the first freelist trunk is
already at its leaf-entry insert limit, so the first obsolete overflow page must
become a new freelist trunk, remaining obsolete overflow pages become leaves on
that trunk, and pointer-map entries across pointer-map pages 2 and 105 are
rewritten to `FREE_PAGE`.

Verification recorded for handoff:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowAutovacuumFreelistSpillCorpusTest.php`
- `php lanes/libsqlite/examples/application-overflow-autovacuum-freelist-spill.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowAutovacuumFreelistSpillCorpusTest.php`
- `php -l lanes/libsqlite/examples/application-overflow-autovacuum-freelist-spill.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is required. This reuses the
native PHP B-tree page, freelist trunk, overflow release, and auto-vacuum
pointer-map primitives already present in `lanes/libsqlite/src`.
