# rowvalue-update-delete-returning-savepoint-current-source-next213

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source savepoint handling with ordered limited subquery tuple sources.

This slice extends `SQLiteUpdateDeleteReturningSql` so row-value `IN (SELECT
... FROM ... WHERE ... ORDER BY ... LIMIT ...)` tuple sources are evaluated
from the provided current table arrays before UPDATE/DELETE RETURNING chooses
rows. `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext213Plan`
then models a failed Application option batch that rolls back to the savepoint
image and retries against a different ordered/limited tuple source.

Application smoke:
`application-rowvalue-order-limit-savepoint-current-source-next213.php` covers a
copied `wp_options` migration where `wp_optionmeta.priority` chooses only the
highest-priority attempted option tuples, then after rollback chooses the
lowest-priority retry tuples before deleting the network row.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext213Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext213Test.php
php -l lanes/libsqlite/examples/application-rowvalue-order-limit-savepoint-current-source-next213.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext213Test.php
php lanes/libsqlite/examples/application-rowvalue-order-limit-savepoint-current-source-next213.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test delta: +44 PASS lines/assertions from the new next213 test file.
Mapped upstream coverage remains unchanged; this is current-source PHP executor
behavior over already mapped row-value UPDATE/DELETE RETURNING inventory.

Non-overlap: avoids accepted row-value next192/202/209/212 savepoint surfaces,
plain row-value SELECT subquery tuple sources, OR FAIL conflict preservation,
trigger, planner, B-tree, WAL/VFS, JSON, and encoding clusters. The new surface
is specifically `ORDER BY` / `LIMIT` inside the row-value subquery source that
feeds UPDATE/DELETE RETURNING under savepoint rollback and retry.

Dependency closure: no new support component is needed. The slice reuses the
native PHP row-array UPDATE/DELETE RETURNING executor and extends its bounded
row-value subquery tuple evaluator.

Next task: continue with a non-overlapping SQL executor/planner or storage
slice; avoid another row-value savepoint variant unless it removes a fresh
current-source blocker.
