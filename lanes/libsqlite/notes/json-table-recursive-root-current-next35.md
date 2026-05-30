# JSON table recursive root current-next35

Slice: `yield-sqlite-json-table-recursive-root-current-next35`.

This adds `SQLiteJsonTableRecursiveRootCursor`, a bounded current/next cursor
for recursive JSON table traversals where the current `json_tree()` /
`json_each()` root rowset can enqueue the next root path. The focused cases
cover:

- seed root queueing, current root advancement, EOF clearing, and duplicate
  root suppression;
- `json_tree()` and `json_each()` roots over text JSON, JSON subtype values,
  JSONB BLOB values, SQL NULL JSON, missing roots, malformed JSONB skip
  behavior, quoted member labels, and negative array roots;
- current-frame row-local ids, parent ids, path/fullkey/root/json column
  preservation, atom-only projections, breadth-first queued root traversal,
  drain limits, and Application plugin-rule `next` traversal.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRecursiveRootCurrentNext35Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-json-table-recursive-root-current-next35.php --self-test
application-json-table-recursive-root-current-next35 self-test passed
```

Non-overlap:

This does not repeat accepted parser-level JSON table SELECT sources, JSON table
cursor iteration, hidden/visible constraint pushdown, JSON host-row joins,
malformed planner diagnostics, JSON table windows, LIMIT/OFFSET handling, or
JSON subtype handoff. The new behavior is specifically current-root to
next-root recursive traversal state for JSON table-valued rows.

Dependency closure:

No new support component is required. The slice reuses existing JSON path,
JSONB, JSON subtype, `json_each()`, and `json_tree()` native PHP components.
