# Real Upstream Corpus B-tree/Index Dynamic Slice

Date: 2026-05-31 02:05 UTC

Base accepted HEAD: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`

Micro-slice: `real-upstream-corpus-btree-index-dynamic-20260531T020230Z-0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexedby.test`
- Sections: `indexedby-11.1` through `indexedby-11.10`
- Scenario: `INDEXED BY` forced covering-index probes may use the rowid or `INTEGER PRIMARY KEY` suffix stored at the end of each secondary-index entry as an equality constraint. The upstream cases cover ordinary integer rowid literals plus text literals `'3'` and `'3.0'` coercing to the same rowid tail lookup.

## Lane Changes

- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexedByRowidTailConstraintCases()`.
- Added `SQLiteRealUpstreamBtreeIndexedByRowidTailDynamicTest.php` with 1000 focused dynamic cases plus corpus count, invalid-size, and dependency-closure checks.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexedByRowidTailDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexedByRowidTailDynamicTest.php` passed: `1 test files, 19340 assertions, 0 failures`, `1003` PASS lines.

## Movement

- Expected selected PASS-line delta: `+1003`.
- Previous lane status selected pass count: `1603992`.
- Updated lane status selected pass count: `1604995`.
- Mapped denominator coverage remains `1589 / 1589`.

## Non-overlap

This slice does not repeat accepted B-tree page relocation, overflow freelist release, indexA partial-affinity/join planner, index2 wide-column, index4 create-index stress, indexexpr JSON covering, or existing indexedby parser/enforcement cases. It owns the upstream `indexedby.test` section 11 rowid-tail equality behavior only.

## Dependency Closure

No new support component is needed. The slice reuses the native B-tree/index dynamic corpus planner, INDEXED BY planner detail records, rowid and `INTEGER PRIMARY KEY` coercion metadata, and covering-index result-row assertions.
