# SELECT JOIN Planner Current-Next72

## Behavior

Adds bounded parser-level support for parenthesized SQLite JOIN source groups in `SQLiteSelectSql`.

Covered cases:

- `(incoming_options AS i JOIN option_labels AS l USING(option_id))` as a base source.
- Parenthesized inner, natural, left, right, and full joins.
- Comma-normalized source groups inside parentheses.
- Nested grouped joins.
- Grouped joins feeding later left/right/full joins.
- CTE and derived-table sources inside a grouped join.
- Rejection of malformed grouped-join aliases and unsupported grouped-join column alias lists.

## Application Relevance

The smoke models a copied `wp_options` import preview that groups a `FULL JOIN`
between current and incoming options before joining labels. This preserves
current-only rows, inserted rows, and updated rows in a single SELECT preview
without requiring `ext/sqlite`.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJoinPlannerCurrentNext72Test.php
Focused test run: 1 selected test files (root lock skipped)
44 PASS lines
1 test files, 86 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-select-join-planner-current-next72.php
[
    {"option_name": "active_plugins", "label": "new-plugin"},
    {"option_name": "blogname", "label": "current-only"},
    {"option_name": "home", "label": "current-only"},
    {"option_name": "siteurl", "label": "existing-url"}
]
```

## Non-Overlap

This does not repeat accepted single-table SELECT SQL text, plain JOIN text
dispatch, right/full JOIN predicate routing, JSON table SELECT sources,
expression ORDER BY, GROUP BY text, or subquery text execution. The new behavior
is specifically parenthesized JOIN source grouping and replay through the
existing SELECT query executor.

## Dependency Closure

No new support component is needed. The slice reuses existing
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectPredicate`,
`SQLiteSelectProjection`, and `SQLiteSelectResult` helpers.
