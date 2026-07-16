# real-upstream-corpus-upsert-returning-dynamic-20260530T210318Z-0

Base accepted HEAD: `6b3b48d963616c004933a32f66ee47ce4ec74885`.

Added `SQLiteRealUpstreamUpsertReturningDynamicCompositeOrderTest.php` with 1,200 focused PASS cases / 2,550 assertions.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
- Sections `upsert3-110`, `upsert3-120`, `upsert3-130`, `upsert3-140`, `upsert3-200`, `upsert3-210`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- Section `returning1-17`

Covered behavior:

- Composite unique indexes accept conflict targets in either declared or reversed column order.
- Partial conflict targets for `k` or `v` alone are rejected for a composite unique constraint.
- Repeated source rows update the same conflict row and preserve the upstream counter sequence.
- `excluded`/base-alias `WHERE` behavior skips a second update after the first update changes the base row.
- Mixed insert/update UPSERT statements emit RETURNING rows in input statement order.
- RETURNING projection aliases remain stable after mixed insert/update batches.

Non-overlap:

This batch avoids the existing upsert1/upsert2/upsert4/upsert5 target, tail, catch-all, priority, correlated, trigger, and broad returning files. It specifically owns the remaining dynamic upsert3 composite-target order and returning1 section 17 mixed insert/update RETURNING order surface.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCompositeOrderTest.php`
- Result: `1 test files, 2550 assertions, 0 failures` and 1,200 PASS lines.

Dependency closure:

No new support component is needed. This reuses the existing bounded row-array UPSERT/RETURNING helper and real upstream SQLite `.test` source files.
