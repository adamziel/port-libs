# trigger-deferred-view-returning-current-source-next131

Status: focused PHP behavior growth for INSTEAD OF view-trigger `RETURNING`
rows at the current-source/deferred-FK boundary.

This slice adds `SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan`.
It applies bounded view-trigger mutations to copied `wp_options` rows, drains
current-source `RETURNING` rows, materializes an autoloaded-options view from
that current source, then performs deferred parent-option validation before
admitting next-source `RETURNING` rows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerDeferredViewReturningCurrentSourceNext131Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-deferred-view-returning-current-source-next131.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredViewReturningCurrentSourceNext131Test.php`
- `php lanes/libsqlite/examples/application-trigger-deferred-view-returning-current-source-next131.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: +67 focused PASS lines after integration, with
`phpPass` updated from `54864` to `54931`. Mapped coverage remains `606 / 1589`
because this is focused PHP behavior over already mapped trigger/view/RETURNING
and deferred-FK surfaces.

Non-overlap: avoids accepted next127 deferred view-trigger release-barrier
savepoint coverage, next128 recursive trigger RETURNING view materialization,
next129 trigger UPSERT RETURNING savepoint behavior, deferred FK cascade
trigger slices, schema reparse/view-trigger slices, row-value RETURNING
slices, and WAL/pager/B-tree/JSON/encoding clusters. The new surface is
INSTEAD OF view-trigger current-source RETURNING drain before deferred FK
admission of next-source rows.

Dependency closure: no new support component is needed. The slice reuses
native PHP row-array mutation, view projection, RETURNING projection, and
deferred foreign-key admission primitives; it does not require ext/sqlite,
upstream binaries, network services, or a new shared dependency row.
