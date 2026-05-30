# trigger-recursive-view-returning-current-source-next185

Status: focused PHP behavior growth for recursive INSTEAD OF view trigger
`RETURNING` streams at the current-source/next-source boundary.

This slice adds a nested recursive depth-drain epoch on top of the accepted
next182 generation fence. A next-source `RETURNING` stream is published only
after the current-source outer rows and all required nested trigger depths have
drained under the expected nested epoch. Missing nested depths, an epoch
mismatch, or a caller that has not requested outer publication keeps the next
source quarantined behind the current source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext185Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next185.php`

Expected dashboard movement: `phpPass +65` from the new focused test file.
Mapped upstream coverage is unchanged; this is current-source PHP behavior over
already mapped trigger/view/RETURNING inventory.

Non-overlap: extends next182 generation fencing with nested recursive
`RETURNING` depth-drain epochs. It avoids accepted next178 snapshot/schema
cookie fencing, next176 page acknowledgements, next181 checkpoints, row-value
RETURNING savepoint clusters, UPSERT/deferred-FK trigger clusters, and all
WAL/VFS/B-tree/JSON/encoding/planner clusters.

Dependency closure: no new support component is needed; this reuses native
recursive view trigger, RETURNING cursor rows, current-source generation
fencing, and nested-depth drain modeling.
