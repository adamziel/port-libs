# trigger-savepoint-returning-recursive-current-source-next122

Adds `SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan`, a
bounded current-source savepoint wrapper around the accepted recursive trigger
UPSERT RETURNING engine.

The new behavior is narrower than accepted next118 and next119: recursive
trigger `RETURNING` rows are yielded from both current-source and next-source
phases, then `ROLLBACK TO` restores the savepoint row image while retaining the
attempted RETURNING/yield stream as diagnostics. A release path commits the
same attempted rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerSavepointReturningRecursiveCurrentSourceNext122Test.php
php -l lanes/libsqlite/examples/wordpress-trigger-savepoint-returning-recursive-current-source-next122.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerSavepointReturningRecursiveCurrentSourceNext122Test.php
php lanes/libsqlite/examples/wordpress-trigger-savepoint-returning-recursive-current-source-next122.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: `phpPass` increases by the focused PASS-line count
from the new test file. `benchmarkDenominator.mapped` is unchanged because this
is a focused PHP behavior slice over already mapped trigger/savepoint/RETURNING
inventory, not a newly hydrated upstream Tcl unit.

Non-overlap: avoids accepted next118 recursive trigger RETURNING current/next
UPSERT, next119 deferred FK RETURNING savepoint rollback, savepoint page-image
rollback, WAL byte truncation, VFS savepoint rollback, and all B-tree/JSON/
planner/encoding clusters. This slice only adds the savepoint current-source
rollback envelope for recursive trigger RETURNING yields.

Dependency closure: no new support component is needed; it reuses the existing
native PHP recursive UPSERT trigger RETURNING engine and lane-local test
harness.
