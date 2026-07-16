# sqlplanner-stat4-covering-range-current-source-next140

## Behavior

Adds a lane-local current-source STAT4 covering range next-cursor plan for stale prepared statements. The slice composes the accepted next138 covering STAT4 range row admission and adds continuity checks for current STAT4 boundary selection, duplicate range-key advancement, stable rowid tie-breaks, and covered payload reads that avoid table seeks.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringRangeCurrentSourceNext140Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-planner-stat4-covering-range-current-source-next140.php --self-test`
- Syntax checks: changed PHP files lint clean
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This reuses `SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan`, `SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan`, and existing CREATE INDEX/stat4 parsing helpers.

## Non-Overlap

Avoids accepted next138 row admission, next131/next135 partial covering, expression-index range cost, expression ORDER BY, skip-scan, JSON table, VFS, WAL, and B-tree clusters. This slice covers current-source STAT4 boundary selection plus stable `Next` advancement across duplicate covering range keys.
