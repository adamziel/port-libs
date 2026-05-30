# PRAGMA schema_version/data_version current next34

This slice extends `SQLitePragmaSchemaDataVersion` beyond the earlier
read/write helper into current SQLite connection-visible behavior:

- same-connection commits advance the file change counter but leave
  `PRAGMA data_version` stable;
- observed external commits advance both the file change counter and the
  connection-visible `data_version`;
- schema changes advance the schema cookie and file change counter while
  preserving same-connection `data_version`;
- reopened header observation updates schema cookie and only reports a
  `data_version` change when the file change counter differs;
- main/temp/attached schemas stay isolated.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaVersionDataCurrentNext34Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaDataVersionCurrentNext25Test.php lanes/libsqlite/tests/SQLitePragmaSchemaVersionDataCurrentNext34Test.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 119 assertions, 0 failures

$ php lanes/libsqlite/examples/application-pragma-schema-version-data-current-next34.php
{
    "scenario": "copied wp_options pragma schema_version/data_version current next34",
    "applicationUse": "Distinguish same-connection import writes from another connection changing a copied SQLite database while preserving schema-cookie and file change-counter preflight rows.",
    "before": {
        "schema_version": 34,
        "data_version": 10
    },
    "localImportCommit": {
        "data_version": 10,
        "changed": false,
        "header": {
            "schema_cookie": 34,
            "file_change_counter": 12
        }
    },
    "externalWriter": {
        "data_version": 11,
        "changed": true,
        "header": {
            "schema_cookie": 34,
            "file_change_counter": 13
        }
    },
    "tempSchemaChange": {
        "schema_version": 5,
        "header": {
            "schema_cookie": 5,
            "file_change_counter": 3
        }
    },
    "observedHeader": {
        "data_version": 20,
        "changed": true,
        "header": {
            "schema_cookie": 36,
            "file_change_counter": 20
        }
    }
}
```

`lane-status.json` moves `phpPass` from `11752` to `11807` by the verified
55 new focused PASS lines. Mapped upstream denominator is unchanged because
this is an additive current-source behavior cluster over already mapped
PRAGMA schema/data-version inventory.

Dependency closure: no new support component is needed; the slice reuses the
existing bounded native PHP PRAGMA header-state component.

Non-overlap: avoids accepted PRAGMA database/table-list, function/module/
collation metadata, schema catalog current-source rebasing, auto-vacuum/
page-count, VFS writer/lock/sync/rollback clusters, WAL checkpoint/savepoint
clusters, JSON table source/cursor/constraint clusters, B-tree page move/
overflow/root-collapse clusters, and SELECT SQL text/subquery/group/order
clusters. The new behavior is specifically the SQLite `data_version`
same-connection vs external-connection distinction and schema-cookie/header
observation model.
