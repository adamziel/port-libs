# trigger-upsert-returning-view-current-source-next144

Status: focused PHP behavior growth for `INSTEAD OF` view-trigger UPSERT `RETURNING` streams across current-source and next-source view definitions.

This slice adds `SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan`. It models a WordPress `wp_options` import routed through an `INSTEAD OF` view trigger where `ON CONFLICT DO UPDATE WHERE` can skip conflicting rows. Skipped rows now produce a diagnostic yield with `returning = null`, do not increment changes, and suppress `RETURNING` output, while changed insert/update rows retain current-source view tokens. A held savepoint keeps the next view source out of the visible stream but still records attempted next-source rows; the release path admits the next view source and its generated `origin` mapping.

WordPress path: `wordpress-trigger-upsert-returning-view-current-source-next144.php` covers a copied `wp_options` import view where plugin migrations add a next-source `origin` column, but the current savepoint still yields only current-source RETURNING rows and skips protected rows via `DO UPDATE WHERE`.

Verification:

```text
$ php -l lanes/libsqlite/src/SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan.php

$ php -l lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewCurrentSourceNext144Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewCurrentSourceNext144Test.php

$ php -l lanes/libsqlite/examples/wordpress-trigger-upsert-returning-view-current-source-next144.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-trigger-upsert-returning-view-current-source-next144.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewCurrentSourceNext144Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 78 assertions, 0 failures

$ php lanes/libsqlite/examples/wordpress-trigger-upsert-returning-view-current-source-next144.php --self-test
wordpress-trigger-upsert-returning-view-current-source-next144 self-test passed
```

Dashboard delta: update `phpPass` by the focused assertion delta verified for the new test file (`+78`, from `63412` to `63490`). `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over already mapped trigger/UPSERT/RETURNING/view surfaces, not a newly hydrated upstream Tcl inventory unit.

Non-overlap: avoids accepted next134 view-trigger savepoint rollback after RETURNING yield, next138 `RAISE(IGNORE)` UPSERT RETURNING suppression, next140 secondary UNIQUE rollback for view-trigger UPSERT, next141 deferred trigger RETURNING, accepted parser-level JSON/SELECT sources, WAL/VFS savepoint application, B-tree freelist/overflow/page-move clusters, and schema view/trigger DDL reparse clusters. The new surface is specifically `DO UPDATE WHERE` skip suppression for RETURNING rows at the current-source to next-source view mapping boundary.

Dependency closure: no new support component is needed. The slice reuses lane-local row-array UPSERT conflict routing, view trigger mapping, RETURNING projection, and current/next savepoint source models.

Next task: wire this skip-state and current/next view source admission into parser-level trigger bytecode execution once native view-trigger UPSERT statements own `DO UPDATE WHERE` directly.
