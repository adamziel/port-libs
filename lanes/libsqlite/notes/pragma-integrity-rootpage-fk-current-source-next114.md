# pragma-integrity-rootpage-fk-current-source-next114

Adds a current-source PRAGMA stream for `foreign_key_check` rows that preserves
the child and parent schema rootpages beside each FK violation, then appends
rootpage-only integrity diagnostics. The cursor source includes database bytes,
schema rows, catalog rootpages, FK SQL, and integrity SQL so resumes fail fast
after rootpage/schema changes.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityRootpageFkCurrentSourceNext114Test.php`
- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityRootpageForeignKeyCurrentSourceYield.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityRootpageFkCurrentSourceNext114Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-integrity-rootpage-fk-current-source-next114.php`
- `php lanes/libsqlite/examples/application-pragma-integrity-rootpage-fk-current-source-next114.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this avoids accepted PRAGMA FK pagination, index/FK admission,
autoindex pointer-map integrity, table-valued FK routing, quick_check
index_xinfo/root wrappers, and pointer-map/freelist integrity slices. The new
surface is FK violation rows annotated with current schema rootpages and a
rootpage-sensitive source cursor.

Dependency closure: no new support component is needed; this reuses the
lane-local attached schema catalog, FK check, integrity check, and current
source cursor primitives.
