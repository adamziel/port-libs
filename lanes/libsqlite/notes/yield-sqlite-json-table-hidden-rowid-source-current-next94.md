# SQLite JSON Table Hidden Rowid Source Current/Next 94

## Behavior

Added `SQLiteJsonTablePlan::currentSourceHiddenRowidPlannerNext94()` for JSON table source switching where `rowid`, `_rowid_`, or `oid` remains a residual hidden constraint over the current JSON source. The helper builds on the accepted current-source and hidden-constraint planners, but adds rowid-specific summaries, row transitions, current/next rowid sets, and replan reasons when the same rowid points at a changed payload, a shifted root, or an empty next rowset.

## Application relevance

The smoke `application-json-table-hidden-rowid-source-current-next94.php` models a copied `wp_options` plugin-settings row where `json_tree()` is pinned to a hidden rowid while a settings import flips a nested `enabled` flag. It reports the current and next rowid sets, payload transition, and next reader policy without requiring `ext/sqlite`.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidSourceCurrentNext94Test.php
```

Result:

```text
1 test files, 55 assertions, 0 failures
```

Additional checks:

```text
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidSourceCurrentNext94Test.php
php -l lanes/libsqlite/examples/application-json-table-hidden-rowid-source-current-next94.php
php lanes/libsqlite/examples/application-json-table-hidden-rowid-source-current-next94.php
```

## Non-overlap

This does not repeat accepted parser-level JSON table SELECT/FROM source wiring, JSON cursor iteration, JSON visible/hidden constraint extraction, lateral rowid host joins, or simple rowid equality SQL execution. It targets the narrower source-current/next rowid residual transition named by this slice.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP JSON table, JSONB, JSON validity, and row materialization components.
