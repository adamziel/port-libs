# trigger-recursive-view-upsert-current-source-next255

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
UPSERT current-source RETURNING cursor drain receipts.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext255Plan`. It
wraps accepted next252 `DO UPDATE ... WHERE` decision receipts and adds a
narrower gate for current-source RETURNING payload drain completion. Next-source
rows remain held until the current RETURNING cursor token matches, required
RETURNING aliases are present on every current payload, every current payload
receipt is acknowledged, and the optional drain-order guard is satisfied.

Application path: `application-trigger-recursive-view-upsert-current-source-next255.php`
models a copied `wp_options` recursive import view where current UPSERT rows
must drain their view-trigger RETURNING cursor before plugin migration rows
from the next source are published.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext255Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext255Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next255.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext255Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next255.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +84` from focused lane-local PASS lines.
Mapped upstream coverage remains unchanged; this is current-source PHP behavior
over already mapped trigger/view/UPSERT inventory, not a newly hydrated upstream
row.

Non-overlap: avoids accepted next251 change-counter fencing, next252 predicate
receipts, next249 assignment receipts, recursive view RETURNING ticket and
generation surfaces, row-value RETURNING, schema reparse, WAL/VFS, JSON table,
planner, encoding, B-tree, and suite-admission clusters. The new surface is
specifically current-source UPSERT RETURNING cursor-drain admission after
`DO UPDATE ... WHERE` receipts are already complete.

Dependency closure: no new support component is needed; this reuses native
recursive view UPSERT rows, current-source `DO UPDATE ... WHERE` receipts, and
existing row-array RETURNING payloads.
