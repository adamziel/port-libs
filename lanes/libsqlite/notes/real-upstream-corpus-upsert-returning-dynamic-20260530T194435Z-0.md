# real-upstream-corpus-upsert-returning-dynamic-20260530T194435Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  sections `upsert2-100`, `upsert2-110`, `upsert2-300`, `upsert2-310`,
  `upsert2-320`, `upsert2-400`, `upsert2-410`, and `upsert2-420`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  section `returning1-4.5`.

Behavior ported:

- `ON CONFLICT DO UPDATE ... WHERE` updates only rows whose current row
  satisfies the conflict WHERE expression.
- Failed `DO UPDATE WHERE` and `DO NOTHING` conflicts fire only the
  before-insert trigger trace and yield no RETURNING row.
- Successful conflict updates fire before-insert, before-update, and
  after-update trace entries in statement order.
- Non-conflicting rows insert, fire after-insert, and appear in the RETURNING
  stream.
- `WITHOUT ROWID` primary-key conflict behavior matches the rowid table path
  for this bounded corpus.

Focused growth:

- Existing focused file coverage: 1416 PASS cases.
- New focused cases added in this slice: 1200 PASS cases.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php`
  passed `1 test files, 2616 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing row-array
  UPSERT/RETURNING executor and adds bounded trigger-trace semantics inside
  `SQLiteUpsertDoUpdateWherePlan`.

Non-overlap:

- This does not add metadata-only admission records or generated fake upstream
  script ids.
- This avoids the already accepted dynamic `upsert5` arm-priority matrix and
  correlated DELETE RETURNING corpus by targeting upstream `upsert2.test`
  trigger firing order and failed-WHERE/DO NOTHING RETURNING visibility.
