# PRAGMA index_xinfo / foreign-key current-source next234

## Behavior

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` for the upstream SQLite parent-key rule that a UNIQUE expression index does not satisfy `REFERENCES parent(column)` parent-key uniqueness.
- Uses `PRAGMA index_xinfo` expression metadata (`cid=-2`, `name=NULL`) alongside `PRAGMA foreign_key_list` parent columns to distinguish:
  - valid column UNIQUE parent keys,
  - expression-backed UNIQUE indexes that must stay blockers,
  - missing non-partial parent UNIQUE indexes.
- Keeps the slice disjoint from accepted next228 DESC-sort compatibility, next229 exact-arity checks, next232 child action-prefix checks, and older parent collation/partial-index coverage.

## WordPress smoke

- `examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next234.php`
- Scenario: copied WordPress import schemas must reject an expression-backed parent index such as `UNIQUE(site_id, lower(slug))` before trusting foreign-key parent-key repair diagnostics for `REFERENCES wp_slug_parent(site_id, slug)`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 78 assertions, 0 failures`
  - `62` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next234.php`
  - self-test passes

## Dependency closure

No new support component is needed. This reuses existing lane-local schema catalog parsing, `PRAGMA index_xinfo`, and `PRAGMA foreign_key_list` helpers.
