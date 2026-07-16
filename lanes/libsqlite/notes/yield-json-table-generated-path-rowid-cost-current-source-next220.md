# JSON Table Generated Path Rowid Cost Current Source Next220

## Slice

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext220()`.
- Extends the existing generated-path/current-source rowid planner chain after next212 `xCurrent` with an `xRowid` admission profile.
- Covers pinned current-source `json_tree()` rowid callback behavior, rowid/_rowid_/oid alias agreement when projected, rowid availability when aliases are not projected, source-change reprepare, range reseek, and cost/replan reason classification.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext220Test.php`
- Result: `1 test files, 56 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next220.php --self-test`
- PHP lint: changed PHP files pass `php -l`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This does not repeat accepted JSON table cursor/source wiring, hidden or visible constraint pushdown, generated-path xFilter/xNext/xColumn/xCurrent profiles, alias order/range profiles, or prior next161/180/186/202/209/212 generated-path rowid cost slices. The new behavior is specifically the virtual-table `xRowid` callback profile after a pinned current-source `xCurrent` row has been materialized.

## Dependency Closure

No new support component is needed. The slice reuses native JSON path parsing, `json_tree()` row generation, generated-path current-source planning, and rowid alias projection already present in `lanes/libsqlite/src`.
