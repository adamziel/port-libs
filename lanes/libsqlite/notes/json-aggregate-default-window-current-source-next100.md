# JSON Aggregate Default Window Current Source Next100

Status: focused PHP behavior growth for parser-level JSON aggregate window
execution when `OVER (...)` omits an explicit frame.

This slice adds SQLite default aggregate-window frame handling for
`json_group_array()`, `jsonb_group_array()`, `json_group_object()`, and
`jsonb_group_object()` inside `SQLiteSelectQuery`. With a window `ORDER BY`,
the default is modeled as peer-aware `RANGE UNBOUNDED PRECEDING` through the
current row. Without a window `ORDER BY`, the frame covers the whole partition.
The existing DISTINCT, FILTER, aggregate-local ORDER BY, JSON subtype, JSONB,
and final ORDER BY paths are reused.

Focused evidence:

```bash
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteJsonAggregateDefaultWindowCurrentSourceNext100Test.php
php -l lanes/libsqlite/examples/application-json-aggregate-default-window-current-source-next100.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDefaultWindowCurrentSourceNext100Test.php
# 1 test files, 67 assertions, 0 failures
# 59 PASS lines
php lanes/libsqlite/examples/application-json-aggregate-default-window-current-source-next100.php
# application-json-aggregate-default-window-current-source-next100 self-test passed
git diff --check -- lanes/libsqlite
```

Dashboard delta: `phpPass` moves from `38766` to `38825` for the 59 newly
passing focused cases. Mapped upstream coverage is unchanged.

Non-overlap: this avoids accepted explicit-frame JSON aggregate DISTINCT/ORDER
window coverage, object aggregate/window coverage, JSON table cursor/source and
constraint work, VFS/WAL/B-tree/storage clusters, grouped SELECT SQL text,
expression ORDER BY, and accepted batch97 attach/btree/encoding/pager/pragma/
planner/VFS/WAL current-source behavior. The narrower surface is the default
aggregate-window frame that SQLite applies when no frame clause is written.

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP SELECT SQL parser, JSON aggregate encoder, JSONB encoder,
predicate filtering, and row-array window executor.
