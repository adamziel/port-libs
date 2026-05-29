# Attach Temp WAL Schema Cookie Bracket Quoted

Status: consolidation replay for ATTACH temp/WAL schema-cookie source attribution.

This slice extends `SQLiteAttachWalTempSchemaCookieSourcePlan` with bracket-quoted identifier extraction and `sqlite_master` to `sqlite_schema` alias normalization. Prepared statements that reference `[main].[sqlite_schema]`, `[temp].[sqlite_master]`, `[site].[wp_options]`, or bracket-quoted DML targets now feed the same current/next schema-cookie reprepare planner as bare and double-quoted names.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCookieSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieBracketQuotedTest.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cookie-bracket-quoted.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieBracketQuotedTest.php`
  - `1 test files, 54 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cookie-bracket-quoted.php --self-test`
  - `wordpress-attach-temp-wal-schema-cookie-bracket-quoted self-test passed`
- `git diff --check -- lanes/libsqlite`

Dashboard delta:

- `phpPass`: unchanged by this consolidation replay; the existing focused coverage is preserved under stable names.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior over an already mapped ATTACH/temp/WAL schema-cookie source surface.

Non-overlap:

This avoids accepted ATTACH WAL/temp rollback routing, temp/WAL schema-cache invalidation, schema-trigger routing, view-trigger/cache current-source handoffs, bracket support in the separate schema-cache extractor, WAL reader/checkpoint/savepoint clusters, VFS file-control/write/sync/lock clusters, B-tree freeblock/freelist/page-move clusters, JSON planner/table work, and SQL SELECT executor clusters. The new surface is identifier extraction and schema-table alias normalization inside the schema-cookie current-source planner itself.

Dependency closure:

No new support component is needed. The slice reuses the lane-local attached schema catalog assumptions, WAL page-one schema-cookie metadata, temp rollback-journal schema-cookie metadata, and prepared statement reprepare planner.

Next task:

Continue with non-overlapping SQL executor/planner, WAL durability, B-tree delete/rebalance, JSON planner, encoding/collation, or suite blocker work. Avoid another attach schema-cache wrapper unless it wires a distinct parser/executor path.
