# SQLite planner multicolumn range current/next25

## Behavior

This slice adds bounded planner evidence for SQLite multicolumn index range behavior:

- equality constraints can form the usable index prefix;
- the first range constraint after that prefix becomes the current B-tree interval;
- later range constraints on following index columns stay residual predicates;
- row estimates are reduced by the equality prefix plus the current range only;
- ORDER BY compatibility is reported from the equality prefix/current range ordering.

The Application smoke uses `wp_options(blog_id, option_name, autoload)` to model multisite option scans where `blog_id = ?` and `option_name >= ?` bound the index walk while an `autoload` range remains a post-scan filter.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerMultiColumnRangeCurrentNext25Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 78 assertions, 0 failures
```

Independent PASS-case delta: `+41` focused `TestRunner` PASS lines.

`lane-status.json` updates `phpPass` from `8739` to `8780`. Mapped upstream coverage is unchanged at `461 / 1589` because this is a focused planner behavior slice, not a new upstream inventory unit.

## Non-overlap

This avoids the accepted batch23 partial-index WHERE implication planner, expression-index range-cost ranking, SQL expression `ORDER BY`, parser-level SELECT/JOIN/GROUP/subquery dispatch, JSON table source/hidden/visible constraint work, VFS/WAL apply paths, and B-tree page/freelist clusters.

## Dependency closure

No new support component is needed. The slice reuses existing `CREATE INDEX` parsing and lane-local predicate payloads.
