# PRAGMA index_xinfo / foreign-key current-source next229

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
PRAGMA helper layered on accepted `index_xinfo`, `foreign_key_list`,
parent-key prefix, and parent-key collation behavior.

New behavior:

- derives explicit foreign-key parent columns from `PRAGMA foreign_key_list`;
- compares them with non-partial UNIQUE parent indexes from
  `PRAGMA index_xinfo`;
- flags the SQLite parent-key admission blocker where the referenced parent
  columns are only the left prefix of a wider UNIQUE index;
- treats exact UNIQUE indexes and exact parent primary keys as admissible;
- reports current/next blocker counts, source summaries, pagination, and
  repair deltas for copied Application taxonomy import schemas.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next229.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next229.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted next224 parent-key collation
matching, next217 prefix/suffix discovery, next218 restrict timing, next211
child nullability, next209 implicit parent primary-key arity, or earlier FK
action/list extraction. The new surface is exact UNIQUE parent-key arity:
`REFERENCES parent(prefix)` must not pass solely because `UNIQUE(prefix, tail)`
exists.

Dependency closure: no new support component is needed. The slice reuses the
schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, and the
existing current-source pagination chain.
