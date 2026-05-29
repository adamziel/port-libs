# PRAGMA integrity_check pointer-map current-source next119

This slice adds `SQLitePragmaIntegrityPointerMapCurrentSourceNext`, a byte-level current/next gate for `PRAGMA integrity_check` pointer-map and freelist diagnostics. It compares current auto-vacuum database bytes with a proposed next source and reports resolved, persisting, and introduced diagnostics before a WordPress copy/import path admits the next database image.

Focused behavior:

- resolved pointer-map diagnostics make the next source ready;
- persisting diagnostics keep `must_block_commit` true;
- introduced diagnostics add an explicit `introduced_pointer_map_integrity` blocker;
- `PRAGMA quick_check` remains countable but does not claim pointer-map diagnostics;
- paginated rows preserve source ids, pointer-map page numbers, entry type, and parent page evidence.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-integrity-pointermap-current-source-next.php`
- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityPointerMapCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-integrity-pointermap-current-source-next.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This does not repeat accepted PRAGMA foreign-key pointer-map pagination, index/root integrity checks, autoindex pointer-map checks, B-tree page move/release materialization, or WAL/VFS durability clusters. It is a current/next admission comparison for raw pointer-map/freelist integrity diagnostics only.

Dependency closure:

No new support component is needed. The slice reuses `SQLitePragmaIntegrityCheck`, `SQLitePragmaIntegrityPointerMapFreelistYield`, `SQLiteDatabase`, and `SQLitePointerMapEntry`.
