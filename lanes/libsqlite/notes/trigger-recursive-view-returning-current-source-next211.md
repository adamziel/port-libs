# trigger-recursive-view-returning-current-source-next211

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` current-source sealing.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext211Plan`.
It layers after the accepted next209 current-source drain watermarks and
computes a deterministic source seal from the drained current `RETURNING` rows,
their trigger-source aliases, ordinals, and current-source watermarks. A next
view/trigger source is published only when the next209 drain is visible, the
current source seal matches, the expected current row count matches, and the
current watermarks remain unique.

Application path:
`application-trigger-recursive-view-returning-current-source-next211.php` models a
copied `wp_options` recursive view import where current trigger-generated
`RETURNING` rows must remain tied to the current view/trigger source before the
next import source can publish `home` / `next_plugin` rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext211Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext211Test.php
php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next211.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext211Test.php
php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next211.php --self-test
```

Focused result: `1 test files, 75 assertions, 0 failures`.

Expected dashboard movement: `phpPass +75` from the new focused test file.
`benchmarkDenominator.mapped` remains `622 / 1589`; this is current-source PHP
behavior over already mapped recursive trigger/view `RETURNING` inventory, not a
fresh hydrated upstream manifest row.

Non-overlap: avoids accepted next208 current cursor close fencing, next209
current-source drain-watermark admission, next203 generation handoff, DML
RETURNING conflict handling, row-value RETURNING savepoints, schema reparse,
WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The new surface is
the source seal that proves the drained current `RETURNING` rows still belong to
the current view/trigger source before publishing next-source rows.

Dependency closure: no new support component is needed. The slice reuses
lane-local recursive view `RETURNING` generation, current-source drain
watermarks, and Application copied `wp_options` view-trigger fixtures.
