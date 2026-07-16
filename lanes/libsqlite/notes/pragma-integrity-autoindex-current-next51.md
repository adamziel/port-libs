### 2026-05-27 PRAGMA integrity autoindex current/next next51

Adds a bounded current/next collector for `PRAGMA integrity_check` autoindex
schema diagnostics. The helper compares declared table UNIQUE/generated UNIQUE
constraints with `sqlite_autoindex_*` schema rows, checks root pages, annotates
auto-vacuum pointer-map pages, and paginates repair-preflight rows for copied
Application `wp_options` databases.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexCurrentNext51Test.php`
- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityAutoindexYield.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexCurrentNext51Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-integrity-autoindex-current-next51.php`
- `php lanes/libsqlite/examples/application-pragma-integrity-autoindex-current-next51.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this avoids accepted batch48 pointer-map/freelist integrity
pagination, rootpage integrity, index/freelist child pointer-map scans,
`PRAGMA index_xinfo`, schema catalog autoindex rows, B-tree page-move/root
collapse/overflow freelist work, VFS/WAL apply clusters, JSON table source and
constraint work, and SELECT SQL text clusters. The new surface is specifically
the integrity-yield view over autoindex declaration/root/pointer-map mismatch
rows.

Dependency closure: no new support component is needed. This reuses existing
native PHP SQLite schema parsing, table constraint autoindex extraction,
database page access, and auto-vacuum pointer-map primitives.
