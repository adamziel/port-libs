# Window Frame EXCLUDE/FILTER Current-Source Next

Status delta: added `SQLiteWindowFrameExcludeFilterCurrentSourceNext`, a
bounded current/next source planner for VDBE-style window frames that composes
`EXCLUDE CURRENT ROW`/`EXCLUDE GROUP`/`EXCLUDE TIES` with aggregate `FILTER`
truthiness across source transitions.

The new planner snapshots current and next row sources with stable source IDs,
drains frame summaries from each source independently, supports resume offsets,
and rejects stale current/next cursors whose source hash no longer matches the
rows and window configuration.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteWindowFrameExcludeFilterCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-window-frame-exclude-filter-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php`
  -> `1 test files, 55 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-window-frame-exclude-filter-current-source-next.php --self-test`
  -> `application-window-frame-exclude-filter-current-source-next self-test passed`

Non-overlap: avoids accepted parser-level SELECT window text, named-window
subqueries, JSON aggregate/window, VDBE GROUPS EXCLUDE/FILTER cursor basics,
VDBE value-window frame reads, sorter NULL/collation window diagnostics,
WAL/VFS/B-tree/storage clusters, and JSON table cursor/source/constraint work.
This slice is the current-source handoff layer that keeps EXCLUDE/FILTER frame
summaries bound to the matching source snapshot before and after a copied
`wp_options` source changes.

Dependency closure: no new support component is needed. The slice reuses
lane-local VDBE window cursor, sorter comparison, aggregate, and source
snapshot primitives.
