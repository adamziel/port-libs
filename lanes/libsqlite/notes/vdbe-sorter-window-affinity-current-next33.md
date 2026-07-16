# VDBE sorter window affinity current-next33

2026-05-27 isolated slice `yield-sqlite-vdbe-sorter-window-affinity-current-next33`.

- Behavior: `SQLiteVdbeWindowAggregateCursor` now exposes current peer rows,
  peer values, peer summaries, and drainable peer summaries over its
  current/next loop. Peer boundaries reuse the VDBE sorter comparator for
  order affinity, collation, descending flags, and NULL placement while still
  respecting partition affinity/collation.
- Application smoke:
  `examples/application-vdbe-sorter-window-affinity-current-next33.php`
  previews copied `wp_options` rows where numeric affinity groups text,
  integer, and BLOB sort keys into CURRENT ROW peers while SQL filter
  truthiness controls aggregate inputs.
- Focused verification:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterWindowAffinityCurrentNext33Test.php`
  passed with `1 test files, 45 assertions, 0 failures`.
- Dependency closure: no new support component is needed; this reuses the
  lane-local VDBE sorter comparator, numeric aggregate, text aggregate, and
  BLOB value primitives.
- Non-overlap: this does not repeat accepted SELECT SQL window text,
  window EXCLUDE/FILTER/GROUPS frame behavior, VDBE sorter distinct collation,
  aggregate ORDER BY cursors, JSON table windows, or storage/VFS/B-tree
  clusters. The new surface is bounded peer CURRENT ROW inspection for window
  sorter streams with affinity/collation-aware current/next boundaries.
