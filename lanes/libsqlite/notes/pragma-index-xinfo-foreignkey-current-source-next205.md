# PRAGMA index_xinfo foreign-key current-source next205

Slice: `pragma-index-xinfo-foreignkey-current-source-next205`.

This adds a current/next diagnostic layer on top of accepted next203 parent
coverage. The new rows use `PRAGMA foreign_key_list` child groups plus
`PRAGMA index_xinfo` prefix metadata to detect child-side FK support indexes
whose prefix columns exist but use the wrong collation or descending order.

WordPress relevance: copied `wp_termmeta` or import staging tables may have
repair indexes present but unusable for stable FK repair planning when copied
collations/order diverge from the declared child columns. The example reports
the mismatch clearing before an import/resume gate proceeds.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next205.php --self-test`

Dependency closure: no new support component is needed; this reuses
`SQLitePragmaSchemaCatalog`, `PRAGMA foreign_key_list`, and `PRAGMA index_xinfo`
metadata already present in the libsqlite lane.

Non-overlap: avoids accepted next203 parent unique coverage, next188 partial
parent unique indexes, next181 parent collation checks, and next183 child
prefix existence checks by adding only child-prefix collation/order quality
diagnostics.
