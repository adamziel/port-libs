# compound-select-values-name-resolution-current-source-next123

Status: focused PHP behavior growth for compound SELECT over VALUES sources.

This slice extends `SQLiteSelectSql` VALUES table references so `(VALUES (...)) AS alias(col, ...)` is accepted, renamed, and exposed with qualified source names. Compound SELECT arms now resolve `alias.column` and unqualified suffix references before `UNION`, `UNION ALL`, `INTERSECT`, and `EXCEPT` rename rows positionally to the left-most result column names.

Application smoke: `application-compound-values-name-resolution-current-source-next123.php` models a plugin import staging list expressed as a VALUES source compounded with copied `wp_options` current/next rows.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundValuesNameResolutionCurrentSourceNext123Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
58 PASS lines
1 test files, 58 assertions, 0 failures
```

```sh
php lanes/libsqlite/examples/application-compound-values-name-resolution-current-source-next123.php --self-test
```

Result:

```text
application-compound-values-name-resolution-current-source-next123 self-test passed
```

Non-overlap: avoids accepted compound row composition, compound recursive limit/current-source next117, compound collation/affinity set-operator behavior, SELECT SQL subqueries/JOIN/GROUP/ORDER/LIMIT clusters, JSON table source/cursor/constraint clusters, and WAL/B-tree/VFS/pager application clusters. The new surface is VALUES source column-alias name resolution feeding compound SELECT current/next source arms.

Dependency closure: no new support component is needed; this reuses the existing SELECT SQL parser/executor, VALUES clause evaluator, source qualification, and compound combiner.

Next task: continue with non-overlapping SQL executor/planner behavior, especially broader VALUES/derived-source name resolution or another compound SELECT gap that is not row composition, recursive queue behavior, collation, or affinity duplicate handling.
