# Source-Neutral STAT4 Key Fields Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-neutral-stat4-20260601T025811Z`
Micro-slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T025811Z-0`
Base accepted HEAD: `515fa94ece8af5512b4751f4654c8d7fe66ba5ec`

## Change

- Updated `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` so the owned STAT4 vector-counter, histogram, sample-anchor, duplicate-peer, scan-direction, and peer-cardinality helpers derive key and tenant columns from index metadata or partial predicate structure.
- Renamed the directly coupled sample-anchor observable proof keys to tenant-oriented names.
- Preserved existing STAT4 counter behavior and direct planner test expectations for row order, sample vectors, stale-counter rejection, duplicate-peer counts, scan anchors, and peer cardinality.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4SampleAnchorFenceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext235Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext242Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4SampleAnchorFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentScanDirectionFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPeerCardinalityTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `7 test files, 436 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "valid json\n";'`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This slice reuses existing STAT4 planner source structures and only changes how the owned helpers discover source key fields.

## Non-Overlap

This source-neutral cleanup avoids accepted throughput clusters for JSON, WAL, VFS, B-tree, expression ORDER BY, range-cost planning, and upstream-suite admission. It only touches the STAT4 expression-partial key-field internals and the directly coupled sample-anchor test assertions.
