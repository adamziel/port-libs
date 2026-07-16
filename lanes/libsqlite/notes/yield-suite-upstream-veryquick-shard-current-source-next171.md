# Upstream Veryquick Shard Current Source Next171

## Slice

- Adds `SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardCurrentSourceNext171()`.
- Admits only focused `veryquick` guarded-runner rows with lane-local artifacts, authoritative current-source provenance, zero-error results, concrete `.test` scripts, no release/all parity claim, and focused TestRunner output.
- Blocks stale source rows, non-veryquick release claims, missing scripts, failed artifacts, lane-external artifacts, and duplicate broad-suite runner overlap.

## Non-Overlap

This shard avoids accepted next117 release gap burnup, next121 release/all countability, current JSON table cursor/source/constraint behavior, VFS rollback/sync/lock/file-writer paths, WAL checkpoint/savepoint byte truncation, B-tree page move/overflow/root-collapse/freeblock surfaces, SQL GROUP BY/JOIN/subquery/expression ORDER BY, and Unicode GLOB behavior.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext171Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS admits current-source next171 veryquick shard artifacts with focused phpPass evidence
PASS blocks current-source next171 veryquick shards with stale provenance and broad-runner overlap

1 test files, 57 assertions, 0 failures
```

Expected dashboard movement: `phpPass +57` from this focused test file. Mapped coverage remains conservative in lane status until the integrator chooses whether to publish the modeled two next171 veryquick countability rows into `UPSTREAM_TEST_MANIFEST.json`.

## Dependency Closure

No new support component is needed. The slice composes existing lane-local runner row metadata, active-runner process snapshots, and focused TestRunner PASS-line output only.
