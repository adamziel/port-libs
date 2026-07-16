# JSON table generated path rowid cost current-source next167

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext167()`.

This composes the accepted generated-path rowid current-source ORDER layer (`next164`) into xFilter-style cursor binding metadata. The profile records argv values/columns/omit flags, ordered output rowids and paths, EOF and pinned-current-source admission, sorter requirements, estimated rows/cost, filter fingerprint transitions, and next-source replan reasons.

Application path: `examples/application-json-table-generated-path-rowid-cost-current-source-next167.php --self-test` models copied `wp_options` plugin-rule JSON where generated path plus `_rowid_ IN (...)` constraints can bind a pinned current `json_tree()` cursor, while a shifted imported next source prepares a fresh filter.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext167Test.php
php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next167.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext167Test.php
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next167.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 58 assertions, 0 failures`.

Neighbor regression result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext167Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext164Test.php` passed with `2 test files, 118 assertions, 0 failures`.

Application smoke result: `application-json-table-generated-path-rowid-cost-current-source-next167 self-test passed`.

Dependency closure: no new support component needed; this reuses native JSON table current-source, generated-path rowid-cost, ORDER, JSON1/JSONB, and planner metadata helpers.

Non-overlap: avoids accepted JSON table SELECT source/cursor wiring, visible/hidden constraint pushdown, generated-path rowid-cost `next145`, current-source/source-cost `next158`/`next160`, seek/admission `next159`/`next161`, best-index metadata `next163`, and current-source ORDER `next164`. This slice only adds the xFilter binding/output layer above the existing current-source plan.
