# yield-sqlite-select-recursive-window-flatten-materialize-current-next53

Status: focused PHP corpus growth for parser-level recursive materialized CTE
rows that feed window current/next yield diagnostics.

This slice adds `SQLiteSelectRecursiveWindowMaterializePlan`, a bounded
composition helper that executes `WITH RECURSIVE ... AS MATERIALIZED` SELECT
SQL, records the CTE flatten/materialize decision, preserves recursive trace
evidence, and emits current/next row transitions with selected window output
columns.

Application smoke:

```sh
php lanes/libsqlite/examples/application-select-recursive-window-flatten-materialize-current-next53.php --self-test
```

Focused test:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectRecursiveWindowFlattenMaterializeCurrentNext53Test.php
```

Expected dashboard movement: add the focused PASS lines from the new test file
to `phpPass`; the verified delta is 20 PASS lines, moving `phpPass` from
19277 to 19297 for this isolated lane patch. Mapped upstream denominator is
unchanged.

Non-overlap: this avoids accepted recursive lateral JSON materialization,
parser-level JSON table SELECT sources, SELECT SQL subqueries, grouped SELECT
SQL text, expression ORDER BY, value-window frame current-next38, VDBE window
cursor/filter clusters, WAL/VFS rollback/checkpoint/write clusters, B-tree
page-move/root-collapse/overflow clusters, and Unicode GLOB work. The new
surface is the combined recursive materialization boundary plus parser-level
window current/next row-yield summary.

Dependency closure: no new support component is needed; this reuses existing
native `SQLiteSelectSql`, `SQLiteSelectQuery`, window helpers, and CTE
flatten/materialize planning.
