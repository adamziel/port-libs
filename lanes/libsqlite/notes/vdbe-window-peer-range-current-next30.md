# VDBE Window Peer RANGE Current Next30

## Scope

- Added bounded `RANGE` frame support to `SQLiteVdbeWindowAggregateCursor` for VDBE-style aggregate cursoring.
- Covers `RANGE BETWEEN CURRENT ROW AND N FOLLOWING` and bounded preceding/following variants over a single numeric ORDER BY key.
- Preserves peer groups for equal ORDER BY values, partition boundaries, SQL FILTER truthiness, descending ORDER BY direction, frame summaries, and aggregate cursor methods.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowPeerRangeCurrentNext30Test.php`
- Result: `1 test files, 56 assertions, 0 failures`

## Non-Overlap

This does not repeat accepted parser-level window RANGE/GROUPS SQL text, JSON table window ranking, or VDBE aggregate ORDER BY cursor behavior. The new behavior is specifically the lower VDBE-style aggregate cursor frame selection for peer-aware numeric RANGE frames.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP VDBE sort comparator and aggregate helpers.
