# yield-sqlite-attach-wal-temp-view-collation-current-next54

Status: focused PHP behavior growth for ATTACHed WAL schema-cookie changes invalidating prepared temp/view trigger collation plans.

Implementation:

- Added `SQLiteAttachWalTempViewCollationPlan`, a bounded native PHP planner that composes existing WAL schema-cookie current/next decisions with existing temp/view trigger collation resolution.
- It reports trigger schema dependencies, changed dependency schemas, expired versus stable prepared trigger plans, target/select/body collation summaries, WAL schema-cookie sources, database-list order, and Application-relevant reprepare trigger names.
- Added `application-attach-wal-temp-view-collation-current-next54.php` as a copied `wp_options` smoke showing main and attached WAL schema-cookie changes expiring prepared view-trigger collation plans while a TEMP-only trigger remains stable.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempViewCollationCurrentNext54Test.php
Focused test run: 1 selected test files (root lock skipped)
62 PASS lines
1 test files, 71 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteAttachWalTempViewCollationPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteAttachWalTempViewCollationPlan.php

php -l lanes/libsqlite/tests/SQLiteAttachWalTempViewCollationCurrentNext54Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteAttachWalTempViewCollationCurrentNext54Test.php

php -l lanes/libsqlite/examples/application-attach-wal-temp-view-collation-current-next54.php
No syntax errors detected in lanes/libsqlite/examples/application-attach-wal-temp-view-collation-current-next54.php

php lanes/libsqlite/examples/application-attach-wal-temp-view-collation-current-next54.php
operation: attach-wal-temp-view-collation-current-next
changed_schemas: main, site
reprepare_triggers: main.main_autoloaded_insert, site.site_autoloaded_insert
stable_triggers: temp.temp_autoloaded_insert

git diff --check -- lanes/libsqlite
ok
```

Expected status delta:

- `phpPass`: `19277 -> 19339` (+62 focused PASS cases).
- `benchmarkDenominator.mapped`: unchanged; this is lane-local current/next behavior coverage, not a newly mapped upstream Tcl inventory unit.

Non-overlap:

This avoids accepted parser-level JSON table source/cursor/hidden/visible constraint work, VFS lock/write/sync/rollback-journal/super-journal clusters, WAL byte truncation and checkpoint transaction clusters, B-tree page/root/overflow clusters, SQL JOIN/GROUP/subquery/ORDER/LIMIT/range-cost clusters, Unicode GLOB, malformed UTF-16 guards, and batch49 ATTACH temp/main WAL schema-cache planning by adding the narrower unhandled interaction between WAL schema-cookie changes and prepared temp/view trigger collation dependency expiration.

Dependency closure:

No new shared support component is needed. The slice reuses lane-local attached schema catalogs, temp/view trigger collation planning, and WAL schema-cookie current/next planning.
