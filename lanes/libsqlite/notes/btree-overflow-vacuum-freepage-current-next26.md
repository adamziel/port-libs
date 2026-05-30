# B-tree Overflow Vacuum Freepage Current/Next 26

## Scope

Adds `SQLiteBTreeOverflowVacuumFreepagePlan`, a bounded B-tree composition helper that starts from accepted overflow delete results, applies their freelist page images into a current database image, and reports the next SQLite freepage allocation order.

This is intentionally narrower than accepted overflow freelist release work: it verifies the current post-vacuum page image state plus next allocation order for copied `wp_options` table/index overflow cleanup.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowVacuumFreepageCurrentNext26Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
59 PASS lines
1 test files, 63 assertions, 0 failures
```

`phpPass` delta: `8739 -> 8798` (`+59` verified PASS lines).

## Application Smoke

`lanes/libsqlite/examples/application-overflow-vacuum-freepage-current-next26.php` reports copied `wp_options` transient table/index overflow pages vacuumed into current freelist page images, with auto-vacuum pointer-map free-page rewrites and bounded next-allocation order.

## Non-Overlap

Avoids accepted batch23 B-tree index delete rebalance and earlier overflow-only/freeblock-only clusters. This patch composes current page images after overflow release with next freepage allocation ordering; it does not repeat table/index leaf bulk overflow freeblocks, overflow freelist release-only assertions, page relocation, root collapse, or index-interior merge.

## Dependency Closure

No new support component is required. The patch reuses existing native PHP SQLite page, freelist, pointer-map, and overflow release primitives.
