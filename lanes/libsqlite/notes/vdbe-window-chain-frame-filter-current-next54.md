# VDBE Window Chain Frame FILTER Current Next54

Status delta: added `SQLiteVdbeWindowChainFramePlan`, a bounded native PHP
planner for VDBE-style window chaining over already ordered cursors. The slice
keeps multiple chained window definitions aligned while peeking current and next
frame summaries without advancing the underlying cursors.

Focused behavior:

- chained `ROWS` and `GROUPS` frames over the same `wp_options`-style ordered
  stream;
- distinct per-window `FILTER` registers (`ok` versus `hot`);
- `EXCLUDE CURRENT ROW` and `EXCLUDE TIES` behavior across current/next peeks;
- aggregate output helpers (`total`, `groupConcat`, `firstValue`,
  `lastValue`, `nthValue`) per chained window;
- stable rewind/drain/manual-next behavior and validation for empty chains,
  invalid names, non-cursor entries, and invalid `nth` indexes.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowChainFrameFilterCurrentNext54Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 50 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vdbe-window-chain-frame-filter-current-next54.php --self-test
application-vdbe-window-chain-frame-filter-current-next54 self-test passed
```

Non-overlap: this does not repeat accepted parser-level SELECT SQL window text,
JSON table windows, VDBE sorter NULL/collation window filtering,
VDBE GROUPS/FILTER/EXCLUDE single-cursor coverage, value-window frame reads,
WAL/VFS/B-tree accepted clusters, or grouped SELECT SQL text. The narrower
surface is chained VDBE window current/next frame peeking across multiple
window cursor definitions with independent FILTER/exclude semantics.

Dependency closure: no new shared support component is needed. The slice reuses
the lane-local VDBE window cursor, sorter comparison, SQL truthiness, and
aggregate helper primitives.
