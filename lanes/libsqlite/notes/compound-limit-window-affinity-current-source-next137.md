# compound-limit-window-affinity-current-source-next137

Status delta: adds `SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan`,
a focused current-source diagnostic for compound SELECT final `LIMIT`/`OFFSET`
over windowed arms where the admitted boundary changes storage class across a
Application option import.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundLimitWindowAffinityCurrentSourceNext137Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 184 assertions, 0 failures
# 58 PASS lines
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-compound-limit-window-affinity-current-source-next137.php --self-test
# application-compound-limit-window-affinity-current-source-next137 self-test passed
```

Syntax/diff checks:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundLimitWindowAffinityCurrentSourceNext137Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundLimitWindowAffinityCurrentSourceNext137Test.php
php -l lanes/libsqlite/examples/application-compound-limit-window-affinity-current-source-next137.php
# No syntax errors detected in lanes/libsqlite/examples/application-compound-limit-window-affinity-current-source-next137.php
git diff --check -- lanes/libsqlite
# passed
```

Dependency closure: no new support component is needed. This reuses the
accepted bounded `SQLiteSelectSql`, compound SELECT, window, and row-array
execution helpers.

Non-overlap: avoids accepted compound row composition, recursive/current-source
compound LIMIT, compound window frame LIMIT next131, compound window EXCEPT
affinity next133, SELECT SQL comma LIMIT, grouped/JOIN/subquery/expression
ORDER BY clusters, JSON table source/cursor/constraint work, and WAL/B-tree/VFS
application clusters. The new surface is the final LIMIT admission boundary
when window arm output and SQLite storage-class affinity diagnostics change
between current and next Application option sources.
