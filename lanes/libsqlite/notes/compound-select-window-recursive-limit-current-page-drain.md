# compound-select-window-recursive-limit-current-source-current-page-drain

## Slice

Adds a current-source drain fence for compound SELECT queries that combine:

- `WITH RECURSIVE` queue `LIMIT/OFFSET`
- per-arm `row_number()` window output
- mixed `UNION ALL`, `INTERSECT`, and `EXCEPT`
- final compound `ORDER BY ... LIMIT ... OFFSET`

The new `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`
reuses the accepted next224 executor coverage, then adds a row-level
acknowledgement contract for the current limited compound page. Staged
next-source rows remain held until the current page's drain token and required
row acknowledgement set match.

## Verification

Run from the repository root:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentPageDrainTest.php
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-page-drain.php
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentPageDrainTest.php
php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-page-drain.php
git diff --check -- lanes/libsqlite
```

## Dependency Closure

No new support component is needed. This reuses native SELECT SQL compound
execution, recursive queue tracing, window rank output, final LIMIT/OFFSET, and
the accepted next224 current-source token fence.

## Non-Overlap

This avoids accepted next224 mixed compound token fencing by adding a separate
current limited-page drain acknowledgement layer. It also avoids accepted
batch107/108 and batch109-113 surfaces, JSON table cursor/source/constraint
work, WAL/VFS durability work, B-tree pointer-map/freeblock work, encoding
LIKE/GLOB work, range-cost planner work, and trigger/RETURNING work.
