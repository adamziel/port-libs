# PRAGMA index_xinfo / foreign-key current-source next163

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, extending the catalog-derived PRAGMA admission path so `REFERENCES parent` with omitted parent columns resolves through the current parent primary key, matching SQLite `foreign_key_list` / `foreign_key_check` behavior.

The slice covers:

- Inline `INTEGER PRIMARY KEY` parent aliases for implicit single-column FKs.
- Table-level composite primary keys for implicit composite FKs.
- WITHOUT ROWID parent primary-key autoindex admission.
- Parent affinity/collation metadata carried into existing FK comparison.
- Stable current/next cursor invalidation when parent PK DDL or table rows change.

The Application smoke models copied multisite `wp_options` rows that reference `wp_blogs` and a WITHOUT ROWID option-scope table without naming parent columns explicitly.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
70 PASS lines
1 test files, 77 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next163.php
{
    "scenario": "application-pragma-index-xinfo-foreignkey-current-source-next163",
    "status": "ok",
    "implicit_parent_keys": 3,
    "current_foreign_key_violations": 2,
    "next_ready": true,
    "delta_total_blockers": -2,
    "applicationUse": "Copied multisite wp_options imports can resume only after PRAGMA index_xinfo and foreign_key_check agree that implicit REFERENCES parent primary-key columns resolve against the current catalog image."
}
```

## Non-overlap

This is not a duplicate of next156-next159. Those slices combine index_xinfo with supplied/catalog FK rows and explicitly named parent columns; next159 still rejected implicit parent columns. This slice handles omitted parent columns by deriving the parent primary-key columns from current schema DDL.

## Dependency closure

No new support component is needed. The slice reuses the existing schema catalog, `index_xinfo`, `foreign_key_list`, parent-index admission, and `foreign_key_check` helpers.
