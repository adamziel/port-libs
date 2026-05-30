# PRAGMA index_xinfo / foreign-key current-source next156

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a bounded current/next cursor for the PRAGMA admission path that combines:

- `PRAGMA index_xinfo(...)` rows for the selected parent index.
- Foreign-key parent UNIQUE-index admission rows backed by `index_xinfo` collation/key metadata.
- `PRAGMA foreign_key_check`-style violation rows from the existing FK checker.
- Stable source hashes and cursor validation across records, FK definitions, table rows, SQL text, and table-valued PRAGMA mode.

The Application smoke models a copied `wp_options` import where the current parent table has an index with the wrong UNIQUE/collation shape and an orphaned option row, while the next source repairs both the parent UNIQUE `NOCASE` index and missing option-name parent row.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
61 PASS lines
1 test files, 69 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next156.php
{
    "scenario": "application-pragma-index-xinfo-foreignkey-current-source-next156",
    "status": "ok",
    "current_index_blockers": 1,
    "current_foreign_key_violations": 1,
    "next_ready": true,
    "delta_total_blockers": -2,
    "parent_index": "wp_option_names_name"
}
```

## Non-overlap

This does not repeat accepted PRAGMA quickcheck/rootpage or index_list work. It uses the existing parent-index admission checker to expose a current/next source cursor for the `index_xinfo` + foreign-key admission surface, separate from accepted rootpage pointer-map checks, quickcheck root blockers, PRAGMA optimize/index_xinfo analysis, and the next148 `index_list`/FK rootpage cluster.

## Dependency closure

No new support component is needed. The slice reuses existing bounded PHP schema catalog, `index_xinfo`, and foreign-key integrity helpers.
