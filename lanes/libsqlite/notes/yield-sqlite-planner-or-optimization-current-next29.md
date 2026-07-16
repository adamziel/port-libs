# SQLite OR Optimization Current Next29

## Scope

Adds bounded planner support for SQLite OR-clause optimization:

- same-column equality OR terms can be rewritten into one indexed `IN` lookup;
- mixed indexed OR terms produce a rowid-union plan with rowid deduplication;
- range and `BETWEEN` OR arms remain residual-checked after index lookup;
- every OR arm must be independently indexable, matching SQLite's fallback rule for unindexed OR terms.

Application relevance: copied `wp_options` reads often combine autoload scans and transient/name lookups, for example `autoload = 'yes' OR option_name >= '_transient_'`, and named option probes such as `option_name = 'siteurl' OR option_name = 'home'`.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteOrOptimizationCurrentNext29Test.php
Focused test run: 1 selected test files (root lock skipped)
54 PASS lines
1 test files, 54 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-or-optimization-current-next29.php
```

## Non-Overlap

This slice avoids accepted SELECT SQL text dispatch, expression `ORDER BY`, expression-index range costs, multi-column AND range planning, JSON table source/cursor/constraint work, VFS writer/sync/lock work, rollback/WAL commit application, and B-tree page-move/freeblock/freelist clusters. It is a distinct planner surface for OR-to-IN and multi-index OR rowid-union decisions.

## Dependency Closure

No new support component is needed. The planner reuses existing `SQLiteCreateIndex::columns()` metadata parsing and stays lane-local in native PHP.
