# Yield SQLite Planner IN/OR Range Current Next35

Status: isolated current-source libsqlite planner behavior slice.

## Behavior

Adds `SQLiteMultiColumnRangePlan::chooseOrRange()` for bounded SQLite OR-range
planning:

- every OR arm must be independently indexable by the existing multicolumn
  equality-prefix plus current-range planner;
- `IN` equality prefixes are retained in the plan and counted as repeated
  current/next seeks;
- compatible OR arms report `single-index-or`, while arms using different
  chosen indexes report `multi-index-or` with rowid-union evidence;
- next-column ranges after the first usable current range remain residual
  predicates.

The Application smoke models copied `wp_options` predicates over
`blog_id IN (...) AND option_name >= ...` OR
`autoload = ... AND option_name BETWEEN ...`, reporting the selected index set
and current/next loop count without requiring `ext/sqlite`.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerInOrRangeCurrentNext35Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
52 PASS lines
1 test files, 90 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-planner-in-or-range-current-next35.php --self-test
```

Result:

```text
application-planner-in-or-range-current-next35 self-test passed
```

## Non-Overlap

This slice does not repeat accepted expression-index range-cost ranking,
batch29 OR optimization evidence, partial-index proof, SELECT SQL expression
ORDER BY, JSON table source/cursor/constraint work, VFS/WAL/B-tree storage
clusters, Unicode GLOB ranges, or rollback/sync writer behavior. It is limited
to current/next planner shape for OR-connected multicolumn range arms and
IN-list equality seek loops.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP index
SQL parsing, `SQLiteMultiColumnRangePlan` predicate normalization, and
lane-local TestRunner verification.
