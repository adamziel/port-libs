# libsqlite suite upstream veryquick shard current-source next311

## Scope

- Removes one focused upstream runner/countability blocker by admitting the
  current-source next311 veryquick shard row.
- Ties the row to launcher Base accepted HEAD
  `a63a17820c8342425b9c9849f5752497926bbaa0` and current integration source
  `8a447f445e5d2fd32fc9fd463117f585d1416551`.
- Counts exact focused TestRunner admission output only: `96` PASS lines,
  `0` failures. The lane-local PHP test verifies the evidence model with
  `1500` assertions.
- Does not claim full release/all parity.

## Non-Overlap

This avoids accepted next155 through next291 veryquick shard evidence,
exact-shard next148, runner106/jsonvt104 rebase items, accepted batch109-113
behavior surfaces, and the live B-tree, JSON, VFS, WAL, planner, PRAGMA,
ATTACH, window, and VDBE workers. The patch is suite-countability only.

## Expected Movement

- `phpPass`: `140230 -> 140326`
- mapped coverage: `694 / 1589 -> 695 / 1589`
- focused test:
  `lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext311Test.php`

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext311Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 1500 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact
rows, source provenance, guarded zero-error runner metadata, duplicate-runner
gates, and focused TestRunner PASS-line output.
