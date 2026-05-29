# compound-select-window-recursive-limit-current-source-next193

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE queue LIMIT/OFFSET rows feed per-arm window evaluation and a current-source signature fences the final compound LIMIT boundary.

Behavior covered:

- `WITH RECURSIVE` queue `LIMIT/OFFSET` rows are traced before compound rows are exposed.
- Window functions in compound arms are evaluated before final compound ordering and LIMIT/OFFSET.
- The current-source signature includes normalized SQL, compound operators/order, recursive emitted/skipped labels, window function names, and the current final-boundary label.
- A stale cursor with a mismatched current-source signature is rejected before next-source rows are admitted.
- Copied WordPress `wp_options` preview rows produce a distinct next-source signature when staged autoload rows move the compound boundary.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext193Test.php
php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next193.php
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext193Test.php
php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next193.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +88` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next189 row-token fence, next188 frame endpoint tape, next186 comma-LIMIT rank/dense-rank behavior, accepted compound recursive/window LIMIT variants, JSON table, VFS/WAL, B-tree, trigger, PRAGMA, encoding, and suite-evidence handoffs. The narrower surface is the current-source signature over recursive trace, window metadata, and the final compound LIMIT boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue, compound combiner, window row-array execution, result LIMIT/OFFSET, and current-source cursor helpers.
