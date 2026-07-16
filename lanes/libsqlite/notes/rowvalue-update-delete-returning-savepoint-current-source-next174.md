# Row-value UPDATE/DELETE RETURNING savepoint current-source next174

Status: focused PHP behavior growth for released inner savepoint row-value
`UPDATE`/`DELETE ... RETURNING` streams.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext174Plan`.
It models a copied Application `wp_options` import batch where an inner savepoint
runs row-value `UPDATE OR REPLACE` plus `DELETE ... RETURNING`, releases those
effects into the outer savepoint, then `ROLLBACK TO` the outer savepoint
discards both the outer and released-inner RETURNING streams before retrying
from the original current source.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext174Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext174Plan.php

php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext174Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext174Test.php

php -l lanes/libsqlite/examples/application-rowvalue-released-inner-rollback-current-source-next174.php
No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-released-inner-rollback-current-source-next174.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext174Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-released-inner-rollback-current-source-next174.php
application-rowvalue-released-inner-rollback-current-source-next174 self-test passed
```

Expected dashboard movement: `phpPass` +59 after clean integration. Mapped
upstream coverage remains unchanged because this is focused current-source
behavior over already mapped row-value DML/savepoint inventory.

Non-overlap: avoids accepted next156 `OR IGNORE`/`OR REPLACE` rollback-to
stream discard, next161/next162 `OR FAIL`, next164 `OR ROLLBACK`, next168
inner rollback-to, next169/next170 `OR ABORT`, trigger/FK RETURNING savepoint,
WAL/pager/VFS savepoint application, B-tree, JSON, encoding, PRAGMA, planner,
and suite-evidence clusters. The new surface is released-inner savepoint
propagation followed by outer `ROLLBACK TO` discard/retry.

Dependency closure: no new support component is needed. The patch reuses
lane-local row-value UPDATE/DELETE RETURNING execution and bounded savepoint
current-source modeling.
