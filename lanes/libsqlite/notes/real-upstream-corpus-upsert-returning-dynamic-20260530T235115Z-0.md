# real-upstream-corpus-upsert-returning-dynamic-20260530T235115Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-1.$tn.1`: primary-key `ON CONFLICT DO NOTHING` leaves the row image unchanged and yields no `RETURNING` rows.
  - `upsert4-1.$tn.2`: secondary unique-column `ON CONFLICT DO NOTHING` leaves the row image unchanged and yields no `RETURNING` rows.
  - `upsert4-1.$tn.3`: explicit unique-column `DO UPDATE` changes the conflicting row, not the incoming row.
  - `upsert4-1.$tn.4`: explicit primary-key `DO UPDATE` changes the primary-key conflicting row.
  - `upsert4-1.$tn.5`: a `DO UPDATE` assignment that violates another unique constraint aborts instead of yielding a `RETURNING` row.
  - `upsert4-1.$tn.8`: a `DO UPDATE` assignment may move the primary key when the final row image remains unique.

Implementation:

- Added `SQLiteRealUpstreamUpsert4ReturningConflictDynamicTest.php`.
- The file uses the existing native `SQLiteUpsertReturningSql` executor against deterministic generic `app_upsert4` rows and checks 1000 seeded variants of the real upstream conflict/update/abort/move sequence.
- No production source change was needed; this exercises already-present native UPSERT conflict-target, secondary-unique, and `RETURNING` row-image behavior.

Focused count:

- 1002 focused TestRunner PASS cases in the new file.
- 1000 dynamic behavior cases, each asserting primary `DO NOTHING`, secondary unique `DO NOTHING`, unique-target update, primary-target update, secondary-unique abort, and primary-key move `RETURNING`/final-row behavior.

Non-overlap:

- This does not repeat the accepted `returning1-17` duplicate row stream, `returning1` scoped name-resolution cases, `autoinc-11.1` explicit-rowid sequence handoff, `upsert5` arm-priority matrix, `upsert2` SELECT input/current-row cases, or trigger/FK/row-value RETURNING helpers.
- This slice owns the `upsert4.test` table-kind conflict behavior through the `RETURNING` row stream using generic application table names.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP UPSERT executor and unique-constraint checks.
