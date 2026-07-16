# PRAGMA integrity/index/FK current-next81

Slice: `pragma-integrity-index-fk-current-next81`.

This patch adds a bounded native PHP current/next stream that composes parent
UNIQUE-index admission rows, live `foreign_key_check` violations, and
`integrity_check` diagnostics into one resumable PRAGMA preflight. The intended
Application import path is copied `wp_options` validation: page from parent-key
metadata checks into row-level FK violations and finally storage/header
integrity blockers without losing current/next cursor state.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield.php
php -l lanes/libsqlite/tests/SQLitePragmaIntegrityIndexForeignKeyCurrentNext81Test.php
php -l lanes/libsqlite/examples/application-pragma-integrity-index-fk-current-next81.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityIndexForeignKeyCurrentNext81Test.php
php lanes/libsqlite/examples/application-pragma-integrity-index-fk-current-next81.php
git diff --check -- lanes/libsqlite
```

Non-overlap: this does not repeat standalone `foreign_key_check`, standalone
FK/index admission current-next71, pointer-map/freelist integrity pagination,
table-scoped integrity, b-tree order integrity, schema PRAGMA catalog rows, or
the accepted batch68/batch75 PRAGMA integrity surfaces. The new behavior is the
combined resumable index/FK/integrity stream and blocker summary.

Dependency closure: no new support component is needed; this reuses existing
native PHP PRAGMA, schema catalog, integrity, and foreign-key helpers.
