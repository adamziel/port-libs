# PRAGMA index_xinfo / foreign-key current-source next217

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
PRAGMA helper layered on accepted `index_xinfo`, `foreign_key_list`, child
action lookup, and implicit-parent-key behavior.

New behavior:

- derives explicit parent-key columns from `PRAGMA foreign_key_list`;
- compares them with `PRAGMA index_xinfo` key-column order for UNIQUE parent
  indexes;
- flags parent keys that are covered only by a suffix of a UNIQUE index, which
  is not a valid SQLite parent key;
- distinguishes suffix-only blockers from partial-UNIQUE and missing-UNIQUE
  parent-key blockers;
- includes parent-key prefix rows in the source hash, pagination contract,
  current/next counts, and repair delta.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next217.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next217.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted next209 implicit parent primary-key
arity, next211 child nullability, next212 child action lookup, next194/210
PRAGMA batch coverage, or accepted integrity/root/foreign-key-check pagination.
The new surface is explicit FK parent-key prefix validation against
`PRAGMA index_xinfo` UNIQUE parent indexes.

Dependency closure: no new support component is needed. The slice reuses the
schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, and the
existing current-source pagination chain.
