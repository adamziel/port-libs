# PRAGMA index_xinfo / foreign_key current-source next247

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, an additive current-source page that layers over next244 and inspects `PRAGMA foreign_key_list` SET DEFAULT actions with `PRAGMA table_info` child-column defaults.

The slice detects WordPress import schemas where `ON DELETE SET DEFAULT` or `ON UPDATE SET DEFAULT` would store `NULL` into a `NOT NULL` child key column because no explicit non-NULL default exists. Nullable child columns with implicit NULL defaults remain admissible, matching SQLite's FK NULL bypass behavior.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- Result: `1 test files, 82 assertions, 0 failures`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-set-default-current-source-next247.php`
- Expected dashboard movement: `phpPass +82` after clean integration; mapped upstream coverage unchanged.

## Non-Overlap

This avoids accepted next244 PRAGMA expression-parent-key behavior, next243 FK affinity behavior, next217 parent UNIQUE prefix behavior, and prior FK action lookup/prefix timing slices. It does not duplicate PRAGMA integrity, foreign_key_check pagination, child action index lookup, parent collation, or FK deferral timing work.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded schema catalog, `foreign_key_list`, `table_info`, and `index_xinfo` helpers.
