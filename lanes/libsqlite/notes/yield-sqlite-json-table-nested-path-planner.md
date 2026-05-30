# SQLite JSON Table Nested Path Planner Current Source Next121

## Behavior

- Added `SQLiteJsonTablePlan::currentSourceNestedPathPlanner()`.
- The planner composes a JSON table root from a source-row base root plus a nested path fragment before delegating to the accepted current-source/cost-order planner.
- Supported nested fragments:
  - empty fragment: reuse base root
  - absolute `$...` path: override base root
  - array fragment such as `[0].rules`: append to base
  - object fragment such as `.rules`: append to base
  - bare label such as `rules`: append as `.rules`

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableNestedPathPlannerTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableNestedPathPlannerTest.php
php -l lanes/libsqlite/examples/application-json-table-nested-path.php
No syntax errors detected
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-nested-path.php
```

The smoke composes `$.plugin.groups[0].rules` and `$.plugin.groups[1].rules` for copied `wp_options` plugin settings and returns ordered priority leaves `[7, 2]` and `[6, 4]`.

## Non-Overlap

This slice does not repeat accepted JSON table cursor behavior, parser-level `json_each`/`json_tree` SELECT sources, hidden/visible constraint extraction, hidden rowid constraints, or next113 cost/order handling. It adds a narrower source-row nested path composition layer on top of those accepted planners.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON path validation, JSON table planning, JSON tree row materialization, and JSONB validation components.
