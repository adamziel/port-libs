# VDBE Window Value GROUPS/RANGE Current-Next 50

This slice adds VDBE cursor helpers for `first_value()`, `last_value()`, and
`nth_value()` over the current window frame. The helpers use unfiltered frame
rows, matching SQLite value-window behavior while preserving the existing
aggregate `FILTER` behavior for `count()`, `sum()`, `total()`, `avg()`, and
`group_concat()`.

Focused coverage is in
`SQLiteVdbeWindowValueGroupsRangeCurrentNext50Test.php` with GROUPS and RANGE
`CURRENT ROW ... FOLLOWING` frames, peer groups, partition boundaries,
`EXCLUDE CURRENT ROW` / `GROUP` / `TIES`, NULL values, and `nth_value()` bounds.

Non-overlap: avoids accepted parser-level GROUP BY/HAVING SQL text, SQL
expression ORDER BY, JSON table cursor/source/constraint work, WAL/VFS
application slices, B-tree page-move/root-collapse/overflow freelist work, and
batch38 VDBE aggregate FILTER/sorter NULL coverage. This is a narrower VDBE
value-function frame readout over GROUPS/RANGE current-next frames.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP VDBE sorter and aggregate cursor model.
