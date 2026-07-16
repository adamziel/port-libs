# real-upstream-corpus-upsert-returning-dynamic-20260531T015418Z-0

Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicScopeMatrixTest.php`, a
5,001 PASS-line / 10,001 assertion dynamic matrix for UPSERT-updated RETURNING
row images and RETURNING name-resolution/scope behavior.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-6.1`: target `RETURNING *` succeeds while `TABLE.*` wildcard
    is rejected.
  - `returning1-7.2` through `returning1-7.8`: `new`, `old`, joined source, and
    target-alias qualifiers are rejected in RETURNING, while target table names
    still resolve.
  - `returning1-8.4`: target-table qualification inside RETURNING resolves the
    modified row image.
- UPSERT-updated row images are produced with the existing native
  `SQLiteUpsertDoUpdateWherePlan`, matching the `upsert1.test` /
  `upsert2.test` conflict-update row-image behavior already used by the lane.

Non-overlap:

- Avoids the accepted `upsert4` alias/table-named-`excluded` matrix,
  `upsert5` catch-all/tail matrices, SELECT-source UPSERT input batches,
  statement-current/excluded batches, and prior RETURNING scope smoke.
- This batch focuses on high-volume target/source qualifier resolution over
  changed UPSERT RETURNING rows.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicScopeMatrixTest.php`
  - `1 test files, 10001 assertions, 0 failures`
  - `5,001` TestRunner PASS lines

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  UPSERT conflict-update executor and RETURNING scoped projection helper.
