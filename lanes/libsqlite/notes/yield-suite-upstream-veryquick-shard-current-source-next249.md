# libsqlite suite upstream veryquick shard current-source next249

## Scope

- Removes one focused upstream runner/countability blocker by admitting the
  current-source next249 veryquick shard row.
- Ties the row to launcher Base accepted HEAD
  `5a62ae8ce825cf04d01f643f93286086483a7ce3` and accepted batch212 source
  `4807cfd284d97b6fed400e910df93fb573cf960a`.
- Counts exact focused TestRunner admission output only: `96` PASS lines,
  `0` failures. The lane-local PHP test verifies the evidence model with
  focused assertions.
- Does not claim full release/all parity.

## Non-Overlap

This avoids accepted next155 through next246 veryquick shard evidence,
exact-shard next148, runner106/jsonvt104 rebase items, accepted batch212
behavior surfaces, and the live B-tree, JSON, VFS, WAL, planner, PRAGMA,
ATTACH, window, and VDBE workers. The patch is suite-countability only.

## Expected Movement

- `phpPass`: `125265 -> 125361`
- mapped coverage: `650 / 1589 -> 651 / 1589`
- focused test:
  `lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext249Test.php`

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext249Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 1500 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact
rows, source provenance, guarded zero-error runner metadata, duplicate-runner
gates, and focused TestRunner PASS-line output.
