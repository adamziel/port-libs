# JSON Table Lateral Rowid Hidden Current Source Next93

Slice: `json-table-lateral-rowid-hidden-current-source-next93`

Behavior: parser-level `json_each()` / `json_tree()` lateral joins now treat
`rowid`, `_rowid_`, and `oid` equality against the current host row as hidden
JSON table `id` constraints. When the JSON table already has dynamic function
arguments, the rowid constraint is appended to the current source instead of
replacing the dynamic `json` / `root` argument tape.

Evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralRowidHiddenCurrentSourceNext93Test.php`
  - `1 test files, 40 assertions, 0 failures`
  - 40 PASS lines
- `php lanes/libsqlite/examples/application-json-table-lateral-rowid-hidden-current-source-next93.php`
  - reports selected copied `wp_options` rules `cache`, `forms`, and `null`
  - reports hidden planner column `id` from `j.rowid = o.target_rowid`

Non-overlap: avoids accepted JSON table cursor/source/hidden/visible
constraint clusters by covering current-host rowid alias extraction for
dynamic lateral sources. It does not repeat parser-level JSON `FROM` wiring,
visible-column pushdown, duplicate hidden `json` / `root` constraints, or
accepted left-join rowid materialization.

Dependency closure: no new support component is needed; this reuses the native
PHP SELECT SQL parser, JSON table planner, and JSON cursor row materialization.
