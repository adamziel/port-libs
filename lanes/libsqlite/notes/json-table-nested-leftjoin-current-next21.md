# JSON table nested LEFT JOIN current-next21

## Behavior

- Adds parser-level SELECT coverage for a nested JSON table LEFT JOIN:
  `wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ... LEFT JOIN json_each(g.value, '$.rules') AS r ...`.
- Fixes the JSON table nullable right-column set so dynamic LEFT JOIN null-extension includes `rowid`, `_rowid_`, and `oid` aliases, matching the aliases projected for matched JSON table rows.
- Covers strict JSON text, JSONB option payloads, empty inner arrays, missing nested paths, empty outer arrays, SQL NULL sources, matched rowid alias filters, and NULL alias filters.

## Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableNestedLeftJoinCurrentNext21Test.php
Focused test run: 1 selected test files (root lock skipped)
37 PASS lines
1 test files, 37 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-select-sql-json-dynamic-join.php
emits nestedLeftJoinRows with copied wp_options JSON-table nested LEFT JOIN diagnostics, including NULL rule_rowid for empty nested arrays.
```

## Status delta

- `phpPass`: `7262 -> 7299` (`+37` verified PASS lines).
- `phpFail`: remains `0`.
- Mapped upstream denominator: unchanged; this is focused native PHP parser/executor coverage, not a newly hydrated upstream Tcl inventory unit.

## Non-overlap

This slice avoids accepted JSON table cursor/source/hidden/visible constraint, host join, SELECT JOIN text, expression ORDER BY, WAL/VFS, B-tree, Unicode GLOB, and rollback/sync clusters. It targets the narrower nested LEFT JOIN rowid-alias NULL-extension behavior.

## Dependency closure

No new support component is needed. The slice reuses existing `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteJsonTablePlan`, JSONB, and row-array execution support.
