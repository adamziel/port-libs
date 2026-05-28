# Trigger Recursive View RETURNING Current Source Next195

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` rows where a next view source cannot resume until every current
source `RETURNING` row has a durable drain receipt.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext195Plan`.
It layers a current-source receipt fence after the accepted next191 payload
fingerprint fence. The plan stamps each current `RETURNING` row with a
source/resume receipt, validates acknowledged receipts, rejects stale current
source or next-resume tokens, and keeps attempted next-source rows hidden until
the current source is fully drained.

WordPress path: `wordpress-trigger-recursive-view-returning-current-source-next195.php`
covers copied `wp_options` import rows flowing through a recursive view trigger.
The current import source must drain before the next copied source can resume
and emit `RETURNING` rows.

Focused verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext195Test.php
php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next195.php
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext195Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext195Test.php
php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next195.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +84` from the new focused test file.
Mapped upstream coverage remains unchanged; this is current-source PHP behavior
over already mapped trigger/view/RETURNING inventory.

Dependency closure: no new support component is needed. The slice reuses the
native recursive view-trigger `RETURNING` row-array model, current-source
fingerprints, and source-token fencing.

Non-overlap: avoids accepted next191 fingerprint fencing, next188 ordinal
watermarks, next184 checkpoint acknowledgements, row-value `RETURNING`,
deferred FK trigger work, schema reparse, WAL/VFS, B-tree, JSON, PRAGMA, and
encoding clusters. The new surface is the final current-source drain receipt
required before next recursive view source resume.
