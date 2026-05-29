# SQL planner expression covering range current-source next146

- Behavior: adds `SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan`, composing the accepted next134 descending covering expression range materializer with a current/next-source fence. The cursor keeps table seeks elided only when schema/stat4/index/row-stream signatures still match the supplied next source.
- Focused tests: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionCoveringRangeCurrentSourceNext146Test.php`.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-expression-covering-range-current-source-next146.php --self-test`.
- PASS delta: +62 focused PASS lines. `lane-status.json` `phpPass` moves from `64226` to `64288`. Mapped upstream coverage remains `606 / 1589`; this is current-source PHP behavior over existing expression-index/range planner inventory rather than a newly hydrated upstream row.
- Non-overlap: avoids accepted next128 range recheck, next134 descending range stream, next138 non-expression STAT4 range, expression ORDER BY, expression-index range-cost ranking, JSON, WAL, VFS, and B-tree clusters.
- Dependency closure: no new support component needed; this reuses native expression-index parsing/planning, STAT4 samples, covering cursor materialization, and current-source fencing.
