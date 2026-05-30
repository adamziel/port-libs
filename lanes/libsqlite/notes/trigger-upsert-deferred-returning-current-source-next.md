# trigger-upsert-deferred-returning-current-source-next137

Implemented `SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan`, a
bounded current-source UPSERT/trigger/RETURNING barrier for deferred validation.
The plan lets current-source UPSERT rows yield `RETURNING`, then detects a
deferred trigger-side parent-key violation at source release, suppresses the
attempted current stream, rolls back to the savepoint source, and admits the
next-source retry rows.

Application smoke:
`application-trigger-upsert-deferred-returning-current-source-next137.php` models
a copied `wp_options` import where plugin trigger rewrites the `siteurl` row
before `RETURNING`, but deferred source validation rejects the current source
and the retry source starts from the original savepoint rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerUpsertDeferredReturningCurrentSourceNext137Test.php
php -l lanes/libsqlite/examples/application-trigger-upsert-deferred-returning-current-source-next137.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertDeferredReturningCurrentSourceNext137Test.php
php lanes/libsqlite/examples/application-trigger-upsert-deferred-returning-current-source-next137.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap: this avoids queued next135 deferred UPSERT commit validation by
working the source-transition retry boundary after current `RETURNING` has
already yielded, and avoids next136 view trigger savepoint behavior. It reuses
the accepted next129 UPSERT RETURNING executor without repeating row-value
UPSERT conflicts, trigger recursive savepoints, deferred FK cascade triggers,
or WAL/VFS savepoint rollback materialization.

Dependency closure: no new support component is needed. The slice reuses the
native PHP current-source UPSERT/trigger/RETURNING row-array executor and adds
only a lane-local deferred source barrier wrapper.
