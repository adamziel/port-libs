# json-table-generated-path-rowid-cost-current-source-next145

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext145()` for the current-source planner boundary where a generated JSON path narrows `json_tree()` output and a hidden rowid alias constraint further intersects that rowset.

The planner now records rowid constraint signatures, path/rowid matched counts, intersected rowids and paths, a generated-path rowid tape, effective cost, cost class transitions, and next145 replan reasons while keeping the current cursor pinned until reset.

Application path: `application-json-table-generated-path-rowid-cost-current-source-next145.php --self-test` models a copied `wp_options` plugin-setting preview where the current source keeps a generated path plus rowid point seek, while the next import shifts sibling rules and prepares a fresh plan.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext145Test.php
# 1 test files, 60 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext145Test.php
php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next145.php

php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next145.php --self-test
# application-json-table-generated-path-rowid-cost-current-source-next145 self-test passed
```

Non-overlap: avoids accepted parser-level JSON table SELECT sources/cursors, hidden and visible constraint extraction, generated hidden rowid cost next142, generated hidden residual cost next141, rowid hidden path next138, generated path cost next134, generated order cost next139, JSON host/dynamic joins, and WAL/B-tree/VFS/encoding clusters. The new surface is generated-path rowid alias cost intersection and current/next transition metadata.

Dependency closure: no new support component is needed; this reuses native JSON table generated-path cost planning and existing rowid alias residual constraint evaluation.
