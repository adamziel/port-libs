# JSON table hidden rowid constraint current-source next116

Slice: `json-table-hidden-rowid-constraint-current-source-next116`

Behavior: parser-level bare `json_each()` / `json_tree()` sources now carry
`WHERE rowid = literal`, `_rowid_ = literal`, and `oid = literal` constraints
into the JSON table plan when the source uses function arguments and an alias.
The pushed rowid constraint is omitted from the residual `WHERE` predicate, so
the current-source row image can be scanned without looking for a qualified
`j.rowid` column after JSON table rows have been unqualified.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidConstraintCurrentSourceNext116Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-hidden-rowid-constraint-current-source-next116.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidConstraintCurrentSourceNext116Test.php`
  - `1 test files, 30 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-hidden-rowid-constraint-current-source-next116.php --self-test`

Application relevance: copied `wp_options` JSON arrays can select a stable
plugin/settings row by JSON table rowid alias while preserving SQLite-style
`rowid` / `_rowid_` / `oid` aliases without requiring `ext/sqlite`.

Dependency closure: no new support component is needed; this reuses the
existing `SQLiteSelectSql` parser/executor and `SQLiteJsonTablePlan`.

Non-overlap: avoids accepted lateral rowid hidden current-source next93,
parser-level JSON table SELECT/FROM source wiring, JSON table cursor behavior,
hidden `json`/`root` extraction, visible-column constraint pushdown, JSON
generated-index expression work, and JSON table hidden rowid batch107/108
rebase. This slice only covers the still-missing bare current-source rowid
alias constraint path for JSON table function calls with arguments.
