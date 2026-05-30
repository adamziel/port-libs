# PRAGMA index_xinfo foreign-key current-source next259-262

Adds real page259-page262 behavior after the accepted next252-next255 handoff.
The slice uses `PRAGMA foreign_key_list`, `PRAGMA index_list`, and
`PRAGMA index_xinfo` to classify child-side lookup indexes used for foreign-key
action probes:

- next259: no child lookup index with the FK child columns as the leftmost key prefix.
- next260: the only matching child lookup index is partial.
- next261: the candidate child lookup index is expression-based instead of plain child columns.
- next262: all child columns are indexed, but not in FK left-prefix order.

The implementation deliberately avoids the blocker-only next260-263 handoff
shape. It does not claim page255-page258; this base ref does not contain those
source primitives.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext259262Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next259-262.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext259262Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next259-262.php --self-test`
- `git diff --check`
