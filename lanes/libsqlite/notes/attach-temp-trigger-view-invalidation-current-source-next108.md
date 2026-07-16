# attach-temp-trigger-view-invalidation-current-source-next108

Status: focused PHP behavior growth for prepared TEMP trigger/view invalidation
when unchanged view SQL resolves to a different current-source table after TEMP
DDL.

This slice adds
`SQLiteAttachTempWalSchemaTriggerPlan::triggerViewInvalidationCurrentSourceNext108()`.
It extends the accepted trigger-view cache and trigger-body dependency work by
resolving view `FROM` dependencies against the current and next schema catalogs.
A copied Application `TEMP VIEW active_options AS ... FROM wp_options` can keep
the same SQL text while a new TEMP `wp_options` staging table appears; the
prepared trigger must finish its current source and report `SQLITE_SCHEMA` on
reset because the view source moves from `main.wp_options` to
`temp.wp_options`.

Application path:
`application-attach-temp-trigger-view-invalidation-current-source-next108.php`
models a copied `wp_options` import trigger over a TEMP view where a plugin
creates a TEMP staging table between current and next sources.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteAttachTempWalSchemaTriggerPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempTriggerViewInvalidationCurrentSourceNext108Test.php
php -l lanes/libsqlite/examples/application-attach-temp-trigger-view-invalidation-current-source-next108.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempTriggerViewInvalidationCurrentSourceNext108Test.php
php lanes/libsqlite/examples/application-attach-temp-trigger-view-invalidation-current-source-next108.php --self-test
git diff --check -- lanes/libsqlite
```

Dashboard delta: `phpPass` should move by the focused PASS-line count verified
for this new test file. `benchmarkDenominator.mapped` is unchanged; this is a
narrow current-source behavior over already mapped ATTACH/TEMP trigger-view
invalidation inventory, not a newly hydrated upstream Tcl unit.

Non-overlap: avoids accepted batch104 ATTACH temp/WAL schema-trigger reprepare,
accepted next97 trigger-view cache raw SQL/column invalidation, accepted next104
trigger-body resolved dependency invalidation, JSON/VFS/WAL/B-tree/encoding
clusters, and SQL SELECT executor work. The new behavior is view dependency
source resolution movement when the view SQL text and trigger SQL text stay
unchanged.

Dependency closure: no new support component is needed. The slice reuses the
lane-local attached schema catalog, TEMP/main search order, trigger-view cache,
and WAL schema-cookie primitives.

Next task: wire the resolved view-dependency invalidation into native prepared
statement bytecode ownership once the broader executor owns view cursor source
snapshots directly.
