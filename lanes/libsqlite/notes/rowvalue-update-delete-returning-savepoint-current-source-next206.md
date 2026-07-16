# rowvalue-update-delete-returning-savepoint-current-source-next206

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext206Plan`.
It models a Application `wp_options` import batch where row-value `UPDATE` and
`DELETE ... RETURNING` statements run inside an inner savepoint, that inner
savepoint is released, and a later `ROLLBACK TO` the outer savepoint discards
both the outer and released-inner `RETURNING` streams. Retry statements then
read the outer savepoint image and publish the new current/next source.

Non-overlap: this does not repeat accepted next200 `OR ABORT` statement
preservation, next203 `OR IGNORE`/`OR REPLACE` conflict handling, or next180
inner `ROLLBACK TO` retry behavior. The new behavior is the released-inner
savepoint handoff being discarded by a later outer `ROLLBACK TO`.

Dependency closure: no new support component is needed; the slice reuses the
existing row-value `SQLiteUpdateDeleteReturningSql` executor and adds
savepoint publication semantics around it.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext206Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext206Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next206.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext206Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next206.php
git diff --check -- lanes/libsqlite
```
