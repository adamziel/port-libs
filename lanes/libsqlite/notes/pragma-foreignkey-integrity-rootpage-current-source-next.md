# PRAGMA Foreign Key Integrity Rootpage Current Source Next140

This slice adds `SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNextPlan`, a current/next comparator for `foreign_key_check` rows enriched with sqlite_schema rootpage and pointer-map state.

The behavior is intentionally disjoint from accepted single-source paging (`next122`), table-scoped integrity filtering (`next128`), and quick-check current/next repair (`next136`): this plan proves whether a proposed next database image, schema row set, and attached catalog clear FK-rootpage blockers before a WordPress SQLite import resumes.

Verification recorded in the handoff:

- `php -l` for changed PHP files
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNext140Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-foreignkey-integrity-rootpage-current-source-next.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is required; the slice reuses the bounded native PHP database image, pointer-map, attached schema catalog, and PRAGMA foreign-key integrity helpers already present in `lanes/libsqlite/src`.
