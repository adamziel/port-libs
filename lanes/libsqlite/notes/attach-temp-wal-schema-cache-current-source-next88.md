# attach-temp-wal-schema-cache-current-source-next88

Status: focused PHP behavior growth for parser-level prepared statement schema-cache extraction.

This slice adds bracket-quoted SQLite identifier support to the bounded schema-cache SQL extractor used by ATTACH/temp/WAL current-source prepared statement lifecycle planning. Prepared statements such as `SELECT ... FROM [main].[wp_options]`, `INSERT INTO [temp].[wp_options_stage]`, and `DELETE FROM [archive].[wp_options]` now normalize to the same schema/table names as bare or double-quoted identifiers before current/next schema-cookie and object-list comparison.

Application relevance: copied Application migration and plugin-cache SQL often preserves bracket-quoted identifiers from admin/export tools. The smoke shows bracket-quoted main, temp, and attached `wp_options` statements expiring or staying reusable after WAL-backed schema writes, temp DDL shadowing, attached archive schema changes, and rollback.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext88Test.php
Focused test run: 1 selected test files (root lock skipped)
57 PASS lines
1 test files, 62 assertions, 0 failures
```

```text
php -l lanes/libsqlite/src/SQLiteAttachTempMainWalSchemaCachePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteAttachTempMainWalSchemaCachePlan.php

php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext88Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext88Test.php

php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next88.php
No syntax errors detected in lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next88.php

php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next88.php --self-test
application-attach-temp-wal-schema-cache-current-source-next88 self-test passed

git diff --check -- lanes/libsqlite
```

Dashboard delta:

- `phpPass`: 34288 -> 34345 (+57 focused PASS lines)
- `phpFail`: unchanged at 0
- `benchmarkDenominator.mapped`: unchanged; this is current-source PHP behavior over the already mapped ATTACH/temp/WAL schema-cache surface, not a newly hydrated upstream inventory row.

Non-overlap: avoids accepted ATTACH WAL/temp rollback routing, trigger/view cache current-source comparisons, SQL attach/detach lifecycle, VFS/file-control/write/sync/lock clusters, WAL checkpoint/savepoint byte paths, B-tree page-move/freelist/overflow clusters, JSON table/source/constraint work, and encoding Unicode GLOB. The new surface is bracket-quoted identifier extraction inside the existing prepared-statement schema-cache current/next path.

Dependency closure: no new support component is needed. The slice reuses the lane-local schema-cache, statement-lifecycle, attached-schema, WAL-cookie, and temp/main name-resolution components.

Next task: wire the same identifier-token normalization into any broader parser/executor path that begins using this schema-cache extractor for full prepared statement planning.
