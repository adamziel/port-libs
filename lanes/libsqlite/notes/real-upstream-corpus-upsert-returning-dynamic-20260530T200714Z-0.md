# real-upstream-corpus-upsert-returning-dynamic-20260530T200714Z-0

Base accepted HEAD: `ab0d9bc9baa20e0418309c1ec67c0447e4a67962`.

Added focused real-upstream PHP coverage for SQLite upstream
`test/upsert5.test` sections `1.$tn.100` through `1.$tn.505`.

Coverage details:

- six upstream schema layouts: rowid primary key first, integer primary key first, WITHOUT ROWID, reversed column order, and reversed WITHOUT ROWID;
- 38 distinct upstream UPSERT cases per schema layout;
- 228 distinct focused TestRunner PASS cases;
- 6,384 focused behavior assertions;
- exercises generalized UPSERT conflict-arm priority, repeated targets, catch-all `ON CONFLICT` arms, `DO NOTHING` short-circuiting, row image preservation, and RETURNING rows for actual mutations.

Non-overlap:

- extends the existing `upsert5.test` dynamic coverage beyond the earlier multi-arm/order smoke ranges by owning the upstream catch-all priority matrix `1.$tn.100-505`;
- does not add generated fake script ids, metadata-only admission rows, WordPress-shaped APIs, or compatibility wrappers.

Dependency closure:

- no new support component is needed;
- reuses the existing native PHP `SQLiteUpsertDoUpdateWherePlan` conflict-arm executor and `SQLiteUpsertReturningDynamicCorpusPlan` hydrated upstream case data.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningCatchAllPriorityDynamicTest.php`
  - `1 test files, 6384 assertions, 0 failures`
