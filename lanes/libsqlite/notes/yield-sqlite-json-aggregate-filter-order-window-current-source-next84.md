# JSON Aggregate FILTER/ORDER Window Current Source Next84

## Behavior

This slice wires parser-level `json_group_array()` and `jsonb_group_array()` window execution through `SQLiteSelectSql` and `SQLiteSelectQuery` for bounded frames:

- aggregate-local `ORDER BY` inside the function argument list;
- `FILTER (WHERE ...)` predicates evaluated against each current source row;
- `ROWS`, `GROUPS`, and numeric `RANGE` bounded frames with `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES`;
- JSON subtype and JSONB payload preservation in window aggregate frames;
- final SELECT `ORDER BY` independence from aggregate-local ordering.

The implementation deliberately avoids previously accepted JSON aggregate DISTINCT/object-window helpers and accepted grouped aggregate materializers. It is a parser/executor bridge for the current-source window form named by this lane.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateFilterOrderWindowCurrentSourceNext84Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
37 PASS lines
1 test files, 71 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-aggregate-filter-order-window-current-source-next84.php
```

Result: emitted copied `wp_options` JSON showing current-row aggregate windows and decoded JSONB frame output.

Syntax checks:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteJsonAggregateFilterOrderWindowCurrentSourceNext84Test.php
php -l lanes/libsqlite/examples/application-json-aggregate-filter-order-window-current-source-next84.php
```

All reported no syntax errors.

## Status Delta

Previous lane-status baseline in this worktree: `31557 PASS / 0 FAIL`.

This slice adds `+37` focused PASS lines, so the expected local `phpPass` after clean integration is `31594 PASS / 0 FAIL`. Mapped upstream coverage is unchanged at `465 / 1589`; no new upstream inventory row is claimed.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded PHP components: `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`, `SQLiteSelectPredicate`, `SQLiteJsonAggregate`, and `SQLiteJsonB`.

## Non-Overlap

Avoided accepted JSON aggregate DISTINCT array/object windows, JSON table cursor/source/hidden/visible constraint work, grouped aggregate JSON summaries, JSONB CHECK admission, and the accepted VFS/WAL/B-tree storage clusters. This patch only adds parser-level JSON array aggregate window execution with `FILTER` and aggregate-local `ORDER BY`.
