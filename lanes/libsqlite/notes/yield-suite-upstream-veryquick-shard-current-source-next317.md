# Upstream veryquick shard current-source next317

## Scope

- Adds `SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext317()`.
- Removes one bounded upstream-runner countability blocker for
  `suite-upstream-veryquick-shard-current-source-next317`.
- Requires lane-local artifact rows, launcher Base accepted HEAD
  `a63a17820c8342425b9c9849f5752497926bbaa0`, current integration source
  `8a447f445e5d2fd32fc9fd463117f585d1416551`, guarded
  `--jobs 1 --stop-on-error veryquick` runner metadata, zero runner errors,
  duplicate-runner blocking, removed-blocker classification, and focused PHP
  PASS-line output.
- Counts exactly `96` focused TestRunner PASS lines and does not claim
  release/all parity.

## Non-Overlap

This is suite/countability evidence only. It avoids accepted next155 through
next291 veryquick-shard rows, queued next292 through next308 suite handoffs,
exact-shard next148, runner106/jsonvt104 rebase work, accepted behavior
surfaces, and live B-tree, JSON, VFS, WAL, planner, PRAGMA, ATTACH, window,
and VDBE implementation work.

## Expected Movement

- `phpPass`: `140230 -> 140326`
- mapped coverage: `694 / 1589 -> 695 / 1589`
- Focused test: `lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext317Test.php`

## Verification

```text
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext317Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext317Test.php
git diff --check -- lanes/libsqlite
```

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact
rows, source provenance, guarded zero-error runner metadata, duplicate-runner
gates, and focused TestRunner PASS-line output.
