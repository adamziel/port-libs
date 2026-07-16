# JSON table numbered method consolidation forty-fourth pass

- Slice: `consolidate-final-numbered-methods-json-table-forty-fourth-pass`
- Base accepted HEAD: `22d5e234ce25f3df34b1198585ab4c2f4d330795`
- Scope: JSON table generated path/rowid cost helper entry points in `SQLiteJsonTablePlan`.

## Consolidation

Renamed four numbered production entry methods to stable descriptive names and
migrated their direct focused tests and Application examples:

- `currentSourceGeneratedPathRowidCostCurrentSourceNext200` ->
  `currentSourceGeneratedPathRowidXFilterArguments`
- `currentSourceGeneratedPathRowidCostCurrentSourceNext202` ->
  `currentSourceGeneratedPathRowidXNextBatch`
- `currentSourceGeneratedPathRowidCostCurrentSourceNext211` ->
  `currentSourceGeneratedPathRowidResumeCursor`
- `currentSourceGeneratedPathRowidCostCurrentSourceNext213` ->
  `currentSourceGeneratedPathRowidResumeStatus`

The plan payload keys and dependency marker strings were intentionally left
unchanged so the migrated tests continue proving the same accepted JSON table
scenarios without reclassifying behavior coverage.

## Verification

- `php -l` on `SQLiteJsonTablePlan.php`, the four changed focused tests, and the
  four changed Application examples: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext200Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext202Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext211Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext213Test.php`: `4 test files, 237 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next200.php --self-test` through `next202`, `next211`, and `next213`: pass.
- `git diff --check -- lanes/libsqlite`: pass.

## Dependency Closure

No new support component is needed. This pass reuses the existing JSON table
planning helpers and only removes numbered production method entry points.

## Follow-up

The adjacent alias projection/order/range JSON table wrappers still have
numbered production method names and should be handled in a separate pass with
their broader family gate, because including the `next203`/`next207` focused
files in this pass reproduces existing assertion failures unrelated to this
renaming subset.
