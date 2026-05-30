# compound-select-window-recursive-limit-current-source-next200

Status: focused PHP behavior growth for parser-level compound SELECT where a recursive CTE queue uses `ORDER BY ... LIMIT/OFFSET`, per-arm `rank()` / `last_value()` windows are evaluated before `UNION` distinct membership, `EXCEPT` removes a stale Application option row, and the final compound `LIMIT/OFFSET` gates the current/next row boundary.

Behavior covered:

- recursive CTE queue `ORDER BY` is preserved while queue `LIMIT/OFFSET` skips the anchor before rows reach compound arms;
- `rank()` and framed `last_value()` window output is materialized before compound distinct comparison;
- `UNION ALL`, `UNION`, and `EXCEPT` membership combine before final `ORDER BY metric DESC, id LIMIT 5 OFFSET 1`;
- a copied next-source `wp_options` autoload row changes the admitted boundary token without reusing a stale cursor.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext200Test.php
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next200.php --self-test
```

Expected dashboard movement: `phpPass +67` from the new focused test file. `benchmarkDenominator.mapped` remains at the current accepted value; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next194 INTERSECT/EXCEPT membership, next192 distribution windows, next191 ntile/lead/nth_value, next190 expression LIMIT, accepted SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is recursive `ORDER BY` queue LIMIT feeding rank/last_value windows before `UNION` distinct plus `EXCEPT` membership.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
