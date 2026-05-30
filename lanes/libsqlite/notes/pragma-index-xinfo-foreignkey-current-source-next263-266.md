# PRAGMA index_xinfo foreign-key current-source next263-266

Adds real page263-page266 behavior after the accepted next259-next262 handoff.
The slice uses `PRAGMA foreign_key_list` action metadata and the child-side
lookup index classification from `PRAGMA index_list` plus `PRAGMA index_xinfo`.

- next263: CASCADE actions whose child lookup index is missing or unusable.
- next264: SET NULL actions whose child lookup index is partial or otherwise unusable.
- next265: SET DEFAULT actions whose child lookup index is expression-based or otherwise unusable.
- next266: RESTRICT actions whose child lookup index exists but is unusable, including misordered prefixes.

The implementation pages current and next rows through the existing current
source cursor chain and reports per-action blockers, repairs, and row summary
deltas without touching unrelated libsqlite areas.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext263266Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next263-266.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext263266Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next263-266.php --self-test`
- `git diff --check`
