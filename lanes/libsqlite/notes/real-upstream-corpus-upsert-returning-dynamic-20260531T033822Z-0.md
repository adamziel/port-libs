# real-upstream-corpus-upsert-returning-dynamic-20260531T033822Z-0

Base accepted HEAD: `eb22516d8f29af7145a28b1cc2453b19311c1d0b`.

Implemented lane-local upstream coverage in:

- `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicAliasSelectTest.php`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `upsert2-200`: SELECT input sees statement-current rows across repeated conflicts.
  - `upsert2-201`: target alias can qualify `DO UPDATE SET` and `WHERE` expressions.
  - `upsert2-202`: base table qualifier is hidden after the INSERT target is aliased.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `17.*`: RETURNING emits one row for each successful INSERT/UPSERT row change.

Coverage shape:

- 1000 deterministic generic `app_metric` row-stream cases compare `SQLiteUpsertReturningSql` against an in-memory SQLite oracle.
- 2 focused parser/error tests cover alias retention and rejected base-table qualification.
- 2 metadata/dependency tests record the source sections and dependency closure.
- Focused result: `1 test files / 4006 assertions / 0 failures / 1004 PASS lines`.

Non-overlap:

- Existing accepted/current tests already cover omitted conflict targets, composite targets, wide conflict-arm priority, and long no-target row streams.
- This slice covers alias-qualified SELECT-input UPSERT with repeated current-row updates and RETURNING stream parity.

Dependency closure:

- No new support component needed; this reuses `SQLiteUpsertReturningSql` SELECT-input parsing, alias-qualified expression evaluation, unique conflict execution, and RETURNING projection.
