# VDBE Aggregate ORDER BY Current/Next24

This isolated slice adds `SQLiteVdbeAggregateOrderByCursor`, a bounded
VDBE-style current/next cursor for aggregate `ORDER BY` clusters. It groups
input rows by one or more GROUP BY columns, exposes original current-group rows,
materializes aggregate-ordered rows per group, supports stable multi-term
`ORDER BY`, `ASC`/`DESC`, explicit `NULLS FIRST`/`NULLS LAST`, `BINARY` and
`NOCASE` text comparison, scalar/BLOB validation, current summaries, and drain
summaries.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAggregateOrderByCurrentNext24Test.php
Focused test run: 1 selected test files (root lock skipped)
46 PASS lines
1 test files, 46 assertions, 0 failures
```

Status delta: `lane-status.json` `phpPass` moves from `8166` to `8212` for the
46 newly verified focused PASS cases. No mapped upstream denominator movement
is claimed.

Application smoke: `examples/application-vdbe-aggregate-orderby-current-next24.php`
prints copied `wp_options` autoload groups with priority-ordered option names
and rowid summary output without requiring `ext/sqlite`.

Non-overlap: this is not the accepted batch21 VDBE sorter distinct/group cursor
surface, and it does not repeat newer accepted SELECT SQL expression `ORDER BY`,
parser-level `GROUP BY` text, JSON table, B-tree, WAL, or VFS clusters. The
cursor specifically models aggregate `ORDER BY` cluster current/next traversal
for VDBE-style aggregate stepping.

Dependency closure: no new shared support component is needed. The slice reuses
lane-local scalar/BLOB value handling and `SQLiteTextAggregate` output
composition.
