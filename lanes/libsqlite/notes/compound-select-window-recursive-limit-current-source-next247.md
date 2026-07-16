# Compound SELECT Window Recursive LIMIT Current Source Next247

Status: focused PHP behavior growth for current-source compound SELECTs where recursive `LIMIT/OFFSET` skipped rows and window metrics must be acknowledged before a yielded next-source cursor can advance.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, layered on accepted next244 recursive LIMIT exhaustion fencing. The new seal binds OFFSET-skipped recursive labels, current and next window result metrics, the yielded next-source cursor, and the next244 recursive fence into one acknowledgement set. Stale seal tokens, stale skipped-row tokens, stale next-cursor tokens, missing acknowledgements, and unexpected acknowledgements reject promotion.

Application path: `application-compound-select-window-recursive-limit-current-source-next247.php` models copied `wp_options` preview queries where a new autoloaded plugin option enters the yielded next source while the current result page still depends on skipped recursive seed rows and window-ranked option rows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext247Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next247.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext247Test.php`
  - Result: `1 test files, 418 assertions, 0 failures`, `77` PASS lines.
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next247.php`
  - Result: emitted `compound-select-window-recursive-limit-current-source-next247-ready` with skipped labels `seed`, `seed:2` and `requiredAckCount` `3`.

Expected dashboard movement: `phpPass +77` from focused lane-local PASS lines. Mapped upstream coverage remains `651 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT/OFFSET inventory.

Dependency closure: no new support component is needed. The slice reuses parser-level SELECT SQL, recursive CTE tracing, compound SELECT result materialization, window metric execution, next244 recursive LIMIT exhaustion fencing, and cursor acknowledgement machinery.

Non-overlap: avoids accepted next244 exhaustion-only fencing, next243 replay tickets, next241 resume admission, next238 source-generation seal, suite247 veryquick-shard evidence, row-value/window RETURNING, trigger recursive UPSERT, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, and VDBE clusters. The narrower behavior is the OFFSET skipped-row/window/next-cursor seal after accepted recursive exhaustion fencing.
