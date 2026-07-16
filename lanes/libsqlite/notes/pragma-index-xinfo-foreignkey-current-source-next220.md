# PRAGMA index_xinfo / foreign-key current-source next220

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, layered on the
accepted next217 parent-key prefix current-source chain.

New behavior:

- derives referenced parent columns from `PRAGMA foreign_key_list`;
- finds the full non-partial UNIQUE parent prefix using `PRAGMA index_xinfo`;
- compares each index term collation with the referenced parent column
  declaration collation;
- flags parent UNIQUE indexes that have the right columns but the wrong
  collation, preserving SQLite foreign-key mismatch semantics;
- includes current/next collation rows in source hashing, pagination, counts,
  deltas, and stale cursor validation.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next220.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next220.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted next200 wrong-order child index
diagnostics, next217 parent UNIQUE prefix validation, next186 child-index
collation checks, or earlier rootpage/integrity/FK pagination clusters. The
new surface is parent-side UNIQUE index collation parity for referenced
foreign-key parent columns.

Dependency closure: no new support component is needed. The slice reuses the
native schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, and
current-source pagination helpers.
