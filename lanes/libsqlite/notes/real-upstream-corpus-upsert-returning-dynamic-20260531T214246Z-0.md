# real-upstream-corpus-upsert-returning-dynamic-20260531T214246Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-14.0`: enables foreign keys and creates `Parent(id INTEGER PRIMARY KEY)` plus `Child(id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES Parent(id))`.
  - `returning1-14.1`: `INSERT INTO child(parent_id) VALUES(123) RETURNING id` reports `FOREIGN KEY constraint failed` before yielding any `RETURNING` row.

Implementation:

- Added `SQLiteReturningForeignKeyBarrierPlan`, a generic immediate foreign-key validation barrier for INSERT ... RETURNING row arrays.
- Added `SQLiteRealUpstreamReturningForeignKeyBarrierDynamicTest.php`.
- The test verifies the hydrated upstream source text, checks a PDO SQLite oracle for the exact FK-before-RETURNING failure, and runs 1000 deterministic generic application variants.
- Each dynamic variant checks invalid child inserts preserve the table and emit no `RETURNING` rows, while valid and NULL-parent child-key inserts still produce a post-insert `RETURNING` row.

Focused count:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningForeignKeyBarrierDynamicTest.php`
- Result: `1 test files, 20007 assertions, 0 failures`.
- Expected lane `phpPass`: `3847998 -> 3868005` (`+20007` focused assertions) if accepted.

Non-overlap:

- Existing accepted UPSERT/RETURNING dynamic files cover conflict arms, `excluded` aliases, trigger streams, fault paths, schema/virtual RETURNING, repeated UPSERT row streams, view target UPSERT rejection, and secondary constraint target matching.
- This slice owns the exact `returning1.test` section 14 immediate foreign-key error barrier before `RETURNING` projection, using generic table names and no WordPress-specific API.

Dependency closure:

- No new support component is needed. The slice reuses generic PHP row arrays and adds bounded immediate foreign-key validation before RETURNING projection.
