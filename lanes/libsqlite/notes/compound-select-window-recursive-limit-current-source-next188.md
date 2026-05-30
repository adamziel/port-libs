# compound-select-window-recursive-limit-current-source-next188

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE `LIMIT/OFFSET` rows feed `first_value()` / `last_value()` window frame endpoints before a final compound `ORDER BY ... LIMIT/OFFSET` current/next boundary.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` and covers:

- recursive queue `LIMIT 4 OFFSET 1` skip/admit tracing before compound row production;
- `first_value()` and `last_value()` endpoint windows evaluated inside compound arms before `UNION` distinct handling;
- a `UNION ALL` head plus `UNION` distinct tail preserving peer-label boundary diagnostics;
- final compound `ORDER BY peer, id LIMIT 6 OFFSET 1` shifting copied `wp_options` rows when the next source adds plugin/theme autoload rows.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext188Test.php
php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next188.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext188Test.php
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next188.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 365 assertions, 0 failures` with `65` PASS lines from the new test file. Expected dashboard movement: `phpPass +65` (`89524 -> 89589`). Mapped coverage remains `616 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next139/156/158/160/162/165/167/168/169/170/171/172/173/175/177/178/181/182/183/184/185 compound window recursive LIMIT surfaces, including lag/lead mixed offsets, named-window expansion, EXCEPT/INTERSECT variants, UNION distinct before UNION ALL row_number/dense_rank/rank behavior, recursive exhaustion pressure, comma LIMIT queues, and accepted SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters. The narrower surface here is `first_value()` / `last_value()` frame endpoint peer labels crossing a post-compound current/next LIMIT boundary.

Dependency closure: no new support component is needed; this reuses lane-local `SQLiteSelectSql`, recursive CTE tracing, compound combiner, window frame execution, and result `ORDER BY` / `LIMIT/OFFSET` machinery.
