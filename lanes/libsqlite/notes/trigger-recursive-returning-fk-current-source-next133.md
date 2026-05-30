# trigger-recursive-returning-fk-current-source-next133

Adds `SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan`, a bounded
current-source handoff wrapper for recursive trigger `RETURNING` statements
with deferred foreign-key admission. It drains the current source first, checks
the deferred FK state, and only then admits the next source. If the current
source rolls back or blocks on deferred FK validation, the next source is not
executed; if the next source later rolls back, its attempted RETURNING rows are
reported separately from the committed stream.

Application relevance: copied `wp_options` refresh/import batches can run
recursive option triggers while metadata rows retain deferred FK references.
The smoke demonstrates current schema-cookie rows yielding before FK admission,
then the next schema-cookie source yielding only after the current source is
valid.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningFkCurrentSourceNext133Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-returning-fk-current-source-next133.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningFkCurrentSourceNext133Test.php`
- Result: `1 test files, 58 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-returning-fk-current-source-next133.php --self-test`
- Result: `application-trigger-recursive-returning-fk-current-source-next133 self-test passed`

Dependency closure: no new support component is needed. This slice reuses the
existing native PHP recursive trigger deferred RETURNING executor from next121
and adds a lane-local current-source FK admission wrapper.

Non-overlap: avoids accepted next111 deferred FK rollback suppression, next121
single-source recursive deferred RETURNING, next122 savepoint wrapper, next127
deferred view RETURNING, and next128 recursive deferred view admission. The new
behavior is two-source admission: the next source is executed only after the
current source's recursive RETURNING stream passes deferred FK validation.
