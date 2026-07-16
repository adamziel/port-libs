# libsqlite suite upstream veryquick shard current-source next240

## Scope

- Removes one focused upstream runner/countability blocker by admitting the
  current-source next240 veryquick shard row.
- Ties the row to launcher Base accepted HEAD
  `44b267059740264471f34081db733c8730a20192` and current integration source
  `8a447f445e5d2fd32fc9fd463117f585d1416551`.
- Counts exact focused TestRunner output only: `96` PASS lines/assertions,
  `0` failures.
- Does not claim full release/all parity.

## Non-Overlap

This avoids accepted next155 through next237 veryquick shard evidence, exact
shard next148, runner106/jsonvt104 rebase items, and the active behavior
surfaces for B-tree, JSON, VFS, WAL, planner, PRAGMA, ATTACH, window, and VDBE
work. The patch is suite-countability only.

## Expected Movement

- `phpPass`: `118322 -> 118418`
- mapped coverage: `641 / 1589 -> 642 / 1589`
- focused test: `lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext240Test.php`

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact
rows, source provenance, guarded zero-error runner metadata, duplicate-runner
gates, and focused TestRunner PASS-line output.
