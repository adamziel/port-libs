# compound-select-window-except-order-current-source

Status: consolidated the former numbered compound SELECT EXCEPT/ORDER helper into stable unsuffixed production method names while preserving the focused parser-level behavior where a windowed current-source rowset feeds `EXCEPT`, then the final compound `ORDER BY` decides the Application option boundary.

Behavior covered:
- `row_number() OVER (PARTITION BY autoload ORDER BY freshness DESC, option_id)` is evaluated before the compound `EXCEPT`.
- Stale copied `wp_option_current` rows remove matching current-source rows by full compound result tuple.
- Next-source `wp_options` rows shift window ranks, so stale rank tuples no longer remove `home` / `rewrite_rules`.
- Tail `ORDER BY source_rank DESC, name` applies after the EXCEPT result is combined.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowExceptOrderCurrentSourceTest.php
```

Result: `1 test files, 195 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-compound-window-except-order-current-source.php --self-test
```

Result: `application-compound-window-except-order-current-source self-test passed`.

Expected dashboard movement: no `phpPass` or mapped-coverage movement; this pass is method-wrapper consolidation over already accepted behavior.

Non-overlap: avoids accepted compound recursive/window LIMIT next139, compound LIMIT/window affinity next137, compound CTE/window ORDER next134, compound VALUES affinity/order next127, EXCEPT/INTERSECT affinity tests, SELECT SQL GROUP/JOIN/subquery/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE window helper slices, and suite evidence handoffs. The narrower surface is window rank materialization before `EXCEPT` and final compound `ORDER BY` over current/next copied Application rows.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, window row-array execution, compound EXCEPT, and result ORDER BY machinery.
