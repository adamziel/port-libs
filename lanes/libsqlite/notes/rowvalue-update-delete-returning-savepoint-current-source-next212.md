# rowvalue-update-delete-returning-savepoint-current-source-next212

This slice adds bounded native PHP support for row-value `IN (SELECT ...)` and
`NOT IN (SELECT ...)` tuple sources in `SQLiteUpdateDeleteReturningSql`. The
subquery source is evaluated from the current row-array tables passed into the
UPDATE/DELETE RETURNING executor, so savepoint rollback and retry statements
see the correct current-source image.

Application smoke:
`application-rowvalue-subquery-savepoint-current-source-next212.php` models a
copied `wp_options` migration where `wp_optionmeta` rows select the tuple set
for option updates and network cleanup deletes. A failed batch rolls back to
the savepoint image, then retry UPDATE/DELETE RETURNING reads the original
current source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext212Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext212Test.php
php -l lanes/libsqlite/examples/application-rowvalue-subquery-savepoint-current-source-next212.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext212Test.php
php lanes/libsqlite/examples/application-rowvalue-subquery-savepoint-current-source-next212.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: focused test growth from the new next212 test file.
Mapped upstream coverage remains conservative because this is PHP executor
behavior over already mapped row-value subquery inventory rather than a new
manifest denominator row.

Non-overlap: avoids accepted row-value next133/176/192/209 savepoint and
conflict-action surfaces, row-value SELECT subquery corpus, row-value literal
and VALUES tuple-list handling, row-value OR/savepoint next209, trigger,
planner, B-tree, WAL/VFS, JSON, and encoding clusters. The new surface is
specifically row-value SELECT subquery tuple sources inside UPDATE/DELETE
RETURNING savepoint current-source retry.

Dependency closure: no new support component is needed. The slice reuses the
existing row-array UPDATE/DELETE RETURNING executor and adds bounded subquery
tuple evaluation against the provided native PHP table arrays.

Next task: continue with a non-overlapping SQL executor/planner or storage
slice; avoid another row-value savepoint variant unless it removes a fresh
current-source blocker.
