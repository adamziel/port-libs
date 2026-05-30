# compound-select-correlated-having-window-current-source-next128

Status delta: adds `SQLiteCompoundHavingWindowCurrentSourceNextPlan`, a
current/next diagnostic wrapper around native `SQLiteSelectSql` compound SELECT
execution for arms that combine aggregate `HAVING` gates, correlated subqueries,
and window projections. The Application smoke models copied `wp_options` current
and staging rows where the next source admits a new plugin flag through the
compound HAVING/window pipeline.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundHavingWindowCurrentSourceNext128Test.php
Focused test run: 1 selected test files (root lock skipped)
45 PASS lines
1 test files, 134 assertions, 0 failures
```

Syntax/example evidence:

```text
php -l lanes/libsqlite/src/SQLiteCompoundHavingWindowCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteCompoundHavingWindowCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteCompoundHavingWindowCurrentSourceNext128Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundHavingWindowCurrentSourceNext128Test.php

php -l lanes/libsqlite/examples/application-compound-having-window-current-source-next128.php
No syntax errors detected in lanes/libsqlite/examples/application-compound-having-window-current-source-next128.php

php lanes/libsqlite/examples/application-compound-having-window-current-source-next128.php --self-test
application-compound-having-window-current-source-next128 self-test passed
```

Non-overlap: avoids accepted compound correlated aggregate next124, compound
correlated window FILTER next126, values affinity/order next127, parser-level
GROUP BY/HAVING text, expression ORDER BY, JSON table source/cursor/constraint
work, VFS/WAL/B-tree clusters, and Unicode GLOB. The new surface is the
current-source diagnostic for correlated aggregate `HAVING` predicates inside
compound arms that also project window functions.

Dependency closure: no new support component is needed; this reuses the native
SELECT SQL compound executor, aggregate HAVING predicates, correlated subquery
predicates, and window projection evaluation.

Next task: after this is accepted, a separate SQL-exec slice can deepen direct
executor behavior for non-diagnostic compound subqueries if it adds fresh
focused assertions without overlapping the accepted compound helper surfaces.
