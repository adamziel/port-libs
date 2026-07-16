# JSON Table Generated Path Rowid Current-Source xBestIndex Next217

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext217()`.
- Reuses the accepted generated-path rowid range and xCurrent chain through
  next212, then records an xBestIndex-style current-source admission profile:
  argv ordering, omitted/residual constraints, idxNum/idxStr, rowid range
  bounds, order consumption, covering-cursor state, estimated rows/cost, and
  stale-source reprepare reasons.
- Application path: copied `wp_options` JSON diagnostics can reuse a
  generated-path `json_tree` rowid range cursor only when the generated-path
  argv, rowid-range argv, rowid order, and current-source xCurrent materialized
  row all remain stable.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext217Test.php`
  - `1 test files, 71 assertions, 0 failures`
  - `71` PASS lines

## Non-Overlap

- Avoids accepted JSON table SELECT source/cursor, hidden/visible constraint
  pushdown, lateral rowid/hidden constraints, and next209/next212 generated
  path rowid range/xCurrent behavior.
- This slice is a narrower xBestIndex current-source admission layer on top of
  the existing generated-path rowid current-source state.

## Dependency Closure

- No new support component needed. The slice reuses native PHP JSON table
  planning, generated-path metadata, rowid range metadata, alias projection,
  xCurrent materialization, and current-source fingerprints.
