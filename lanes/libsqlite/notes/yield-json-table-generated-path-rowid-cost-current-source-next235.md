# JSON table generated path rowid current-source yield next235

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext235()`.
- Extends the accepted next224 xCurrent yield guard with a current-source yield tape that records:
  - delivered rowids;
  - resume rowids;
  - projected JSON table row payload;
  - current-source yield fingerprints;
  - stale fingerprint / stale rowid restart decisions;
  - reprepare decisions when the next source invalidates the generated-path rowid cursor.

## Non-overlap

This slice does not repeat accepted JSON visible constraints, JSON hidden constraints, JSON table SELECT source/cursor behavior, or next200/next224 generated-path rowid xFilter/xCurrent guards. It adds the narrower post-guard yield-tape admission step for current-source reuse.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext235Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next235.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext235Test.php`
  - `1 test files, 63 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next235.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next235 self-test passed`

## Dependency Closure

No new support component is needed; this reuses native JSON table generated-path rowid xCurrent yield guards, current-source fingerprints, and rowid resume tapes.
