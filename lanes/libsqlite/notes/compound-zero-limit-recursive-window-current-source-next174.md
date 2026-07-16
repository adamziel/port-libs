# compound-zero-limit-recursive-window-current-source-next174

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE rows and Application option rows are windowed and combined, but a final compound `LIMIT 0` suppresses all visible rows.

Behavior covered:

- Recursive CTE queue rows are still traceable before the compound tail limit is applied.
- Window functions in both compound arms are planned before final compound result trimming.
- `LIMIT 0 OFFSET n` yields no visible rows while preserving suppressed current/next diagnostics for reprepare decisions.
- A next-source copied `wp_options` row can change the suppressed pre-limit rowset even though both visible rowsets are empty.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNext174Test.php
php -l lanes/libsqlite/examples/application-compound-zero-limit-recursive-window-current-source-next174.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNext174Test.php
php lanes/libsqlite/examples/application-compound-zero-limit-recursive-window-current-source-next174.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +66` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged because this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted compound next139/next157/next158/next162/next166/next170 variants and queued next171/next172/next173 handoffs by focusing on a final compound `LIMIT 0` suppression boundary, not INTERSECT/EXCEPT retention, recursive affinity, queue `LIMIT 0`, final non-zero LIMIT/OFFSET, or current/next admitted row movement.

Dependency closure: no new support component is needed; this reuses existing lane-local `SQLiteSelectSql` recursive CTE tracing, compound SELECT execution, window row-array evaluation, and final LIMIT/OFFSET result trimming.
