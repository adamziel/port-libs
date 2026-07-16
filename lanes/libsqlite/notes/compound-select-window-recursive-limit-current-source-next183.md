# Compound SELECT Window Recursive LIMIT Current Source Next183

## Behavior

- Adds lane-local current-source coverage for a recursive CTE with `LIMIT/OFFSET`
  feeding `lag()` and `lead()` window arms before `UNION ALL` plus `UNION`
  distinct compound processing and final `ORDER BY ... LIMIT ... OFFSET`.
- The Application smoke models copied `wp_options` import previews where
  next-source plugin/theme options cross the final compound LIMIT boundary
  after recursive rows and window metrics have already been evaluated.

## Evidence

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext183Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next183.php`
- Dependency closure: no new support component needed; this reuses native
  SELECT SQL recursive CTE, compound, window, ORDER BY, and LIMIT/OFFSET helpers.

## Non-Overlap

This slice avoids accepted JSON table, WAL, B-tree, VFS, encoding, schema,
trigger, and suite-runner surfaces, and does not repeat accepted standalone
compound row composition. It narrows the SQL executor path to current-source
boundary changes when recursive LIMIT/OFFSET rows feed windowed compound arms
before final compound tail limiting.
