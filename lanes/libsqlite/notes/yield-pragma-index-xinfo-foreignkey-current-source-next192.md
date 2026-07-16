# PRAGMA index_xinfo foreign-key current-source next192

## Behavior

Adds a current/next PRAGMA catalog page for a parent UNIQUE index that has the
right parent key columns but the wrong collating sequence for FK enforcement.
SQLite rejects that index as a parent key even though `PRAGMA index_xinfo`
shows matching column names, because the parent column declaration collation
must match the UNIQUE index key collation.

This extends the accepted next189 rejected-parent-unique work without repeating
partial UNIQUE or expression UNIQUE handling. The new rows are
`foreign_key_rejected_parent_collation` and decorate parent-key rows with
`rejected_parent_unique_reason = parent_collation_mismatch`.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next192.php --self-test`

## Dependency Closure

No new support component is needed. The slice reuses the existing schema record
catalog, `PRAGMA index_xinfo`, `PRAGMA index_list`, and foreign-key catalog
parsing helpers.

## Non-Overlap

Avoids accepted next175 foreign-key list rows, next183 child-index prefix
coverage, next186 child-index collation checks, next188 partial parent UNIQUE
diagnostics, and next189 rejected partial/expression parent UNIQUE diagnostics.
