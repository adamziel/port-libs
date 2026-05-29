# PRAGMA index_xinfo / foreign-key current-source next159

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a catalog-derived current/next cursor for the PRAGMA admission path that combines:

- `PRAGMA index_xinfo(...)` rows for the selected parent index.
- `PRAGMA foreign_key_list(...)` extraction from `sqlite_schema` table DDL instead of caller-supplied FK arrays.
- Parent-column affinity and collation enrichment so `foreign_key_check` uses the upstream parent key comparison rules.
- Existing parent UNIQUE-index admission checks and `foreign_key_check` violation rows.
- Stable current/next cursor validation through the accepted next156 cursor.

The WordPress smoke models copied multisite `wp_options` data where the current parent index has a mismatched `BINARY` collation and missing parent rows, while the next source repairs the parent `NOCASE` UNIQUE index and missing parent rows.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
74 PASS lines
1 test files, 82 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next159.php
{
    "scenario": "wordpress-pragma-index-xinfo-foreignkey-current-source-next159",
    "status": "ok",
    "foreign_key_source": "pragma_foreign_key_list",
    "derived_foreign_keys": 2,
    "current_index_blockers": 1,
    "current_foreign_key_violations": 2,
    "next_ready": true,
    "delta_total_blockers": -3
}
```

## Non-overlap

This is not a duplicate of accepted next156. next156 combines caller-supplied FK arrays with `index_xinfo`; next159 derives FK definitions from catalog `PRAGMA foreign_key_list` output and preserves parent affinity/collation for FK comparison. It also avoids accepted quickcheck/rootpage, index_list, PRAGMA optimize/index_xinfo analysis, visible rootpage, pointer-map, and foreign-key pagination clusters.

## Dependency closure

No new support component is needed. The slice reuses the existing schema catalog, `index_xinfo`, `foreign_key_list`, parent-index admission, and `foreign_key_check` helpers.
