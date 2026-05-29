# Attach Temp Main WAL Schema Cache

Adds `SQLiteAttachTempMainWalSchemaCachePlan`, a bounded native-PHP planner for
ATTACH/temp/main schema-cache behavior when WAL page-1 schema
cookie changes are visible to the next statement. The slice verifies that
unqualified `wp_options` can remain bound to temp while main/attached WAL schema
cookies still force qualified prepared statements to reprepare.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempMainWalSchemaCacheTest.php`
- `php -l lanes/libsqlite/src/SQLiteAttachTempMainWalSchemaCachePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempMainWalSchemaCacheTest.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-main-wal-schema-cache.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-main-wal-schema-cache.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses
lane-local WAL/schema-cache concepts and adds only a bounded planner class.

Non-overlap: this does not repeat accepted ATTACH URI schema-cache reuse,
ATTACH temp/main collation shadowing, parser-level JSON table source wiring,
WAL checkpoint transactions, VFS file writer/sync/lock/rollback paths,
B-tree page relocation/root collapse/overflow freelist release, SQL expression
ORDER BY, grouped SELECT text, or Unicode GLOB. The new behavior is the
current/next schema-cookie reprepare decision across temp, main, and attached
schemas when WAL page-1 changes arrive after an ATTACH-style schema set.
