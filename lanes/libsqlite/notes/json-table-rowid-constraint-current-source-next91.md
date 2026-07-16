# JSON Table Rowid Constraint Current Source Next91

Behavior slice: JSON table planner constraints now normalize `rowid`,
`_rowid_`, and `oid` to the virtual table `id` column before xBestIndex-style
planning. This makes direct current-source plans use the same narrow visible
constraint tape and estimates as parser-level SELECT SQL rowid constraints.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRowidConstraintCurrentSourceNext91Test.php`
- Result: `1 test files, 46 assertions, 0 failures` with 46 PASS lines.

Application smoke:

- `php lanes/libsqlite/examples/application-json-table-rowid-constraint-current-source-next91.php --self-test`
- Reports copied `wp_options` plugin-rule JSON scans where rowid constraints
  are planned as `visible:id:=` against current and next option values.

Non-overlap:

- Avoids accepted parser-level JSON table SELECT source/cursor behavior,
  hidden/visible json/root/key/type/atom constraint pushdown, lateral rowid
  projection, nested/left-join rowid regressions, and batch88 hidden-constraint
  current-source planning. This slice only fixes lower-level planner admission
  for direct `rowid`/`_rowid_`/`oid` constraints against current/next JSON
  sources.

Dependency closure:

- No new support component is needed. The patch reuses existing native PHP
  JSON table, JSON path, JSONB, and current-source planner components.

Next:

- Continue with non-overlapping JSON planner work such as malformed JSONB
  planner edges or dynamic join constraints beyond rowid alias normalization.
