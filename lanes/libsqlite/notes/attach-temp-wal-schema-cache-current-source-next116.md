# attach-temp-wal-schema-cache-current-source-next116

Status: focused PHP behavior growth for ATTACH/temp/WAL prepared statement schema-cache expiry when SQL uses `INDEXED BY`.

This slice extends the current-source schema-cache planner with bounded index dependency tracking:

- `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext116()` extracts `INDEXED BY` index names from `SELECT`, `UPDATE`, `DELETE`, and joined table references.
- The planner now accepts schema `indexes` metadata and index DDL events (`create_index`, `drop_index`), advances schema cookies, and reports per-statement `index_transitions`.
- Prepared statements expire when their required index disappears, appears, moves through ATTACH/DETACH resolution, or when the containing schema cookie changes. Unrelated attached-schema index DDL does not expire a stable statement.

WordPress relevance: copied `wp_options` migration SQL commonly pins index use around `autoload`, staging-table names, or archive option names. The smoke shows main/temp/attached `INDEXED BY` statements expiring after WAL-backed main index DDL, temp staging index DDL, and attached archive index DDL while a stable multisite reader stays reusable.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext116Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next116.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext116Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next116.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 50 assertions, 0 failures
wordpress-attach-temp-wal-schema-cache-current-source-next116 self-test passed
```

Dashboard delta:

- `phpPass`: `43574 -> 43624` from 50 newly verified focused assertions.
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged; this is current-source PHP behavior over the already mapped ATTACH/temp/WAL schema-cache surface, not a newly hydrated upstream inventory row.

Non-overlap: avoids accepted ATTACH schema-cache DDL/temp trigger-view invalidation, bracket identifier extraction next88, CTE dependency extraction next100, schema generated-trigger reparse, PRAGMA index metadata, VFS/WAL/B-tree/JSON/encoding accepted clusters, and queued VFS SHM/file-control next112, JSON table next113, and B-tree freelist next113 surfaces. The new surface is `INDEXED BY` index dependency expiry inside the prepared statement schema-cache current/next path.

Dependency closure: no new support component is needed. The slice reuses lane-local ATTACH/temp/WAL schema-cache planning, WAL page-one schema-cookie handling, and prepared-statement lifecycle metadata.

Next task: wire index dependency transitions into any broader parser/executor prepared-statement cache path that begins executing these statements natively.
