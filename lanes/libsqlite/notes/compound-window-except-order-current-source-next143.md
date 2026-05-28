# compound-select-window-except-order-current-source-next143

Status: focused PHP behavior growth for parser-level compound SELECT output where a windowed current-source rowset feeds `EXCEPT`, then the final compound `ORDER BY` decides the WordPress option boundary.

Behavior covered:
- `row_number() OVER (PARTITION BY autoload ORDER BY freshness DESC, option_id)` is evaluated before the compound `EXCEPT`.
- Stale copied `wp_option_current` rows remove matching current-source rows by full compound result tuple.
- Next-source `wp_options` rows shift window ranks, so stale rank tuples no longer remove `home` / `rewrite_rules`.
- Tail `ORDER BY source_rank DESC, name` applies after the EXCEPT result is combined.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowExceptOrderCurrentSourceNext143Test.php
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-compound-window-except-order-current-source-next143.php --self-test
```

Expected dashboard movement: `phpPass +64` from the new focused test file. `benchmarkDenominator.mapped` remains `606 / 1589`; this is current-source PHP behavior over already mapped compound SELECT, window, EXCEPT, and ORDER inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound recursive/window LIMIT next139, compound LIMIT/window affinity next137, compound CTE/window ORDER next134, compound VALUES affinity/order next127, EXCEPT/INTERSECT affinity tests, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is window rank materialization before `EXCEPT` and final compound `ORDER BY` over current/next copied WordPress rows.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, window row-array execution, compound EXCEPT, and result ORDER BY machinery.
