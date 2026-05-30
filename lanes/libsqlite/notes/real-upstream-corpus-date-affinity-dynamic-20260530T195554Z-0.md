# real-upstream-corpus-date-affinity-dynamic-20260530T195554Z-0

Status: focused real-upstream corpus test growth for SQLite date/time affinity.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `date3-1.7`: `unixepoch(x,'unixepoch')==x` over generated timestamp values.

Coverage added:

- 1 cite/provenance PASS case for the hydrated upstream file and scenario.
- 1000 deterministic timestamp PASS cases porting the upstream generated `date3-1.7` unixepoch roundtrip loop without Tcl randomness.
- 1 generic application retention-window PASS case proving integer timestamp affinity works through `SQLiteSelectSql` filtering and ordering.

Non-overlap:

- Existing date-affinity dynamic files cover `date.test` fractional milliseconds, `date2.test` schema determinism, `date3.test` auto/unixepoch samples, `date4.test`, `date5.test`, `affinity2.test`, `affinity3.test`, and `types3.test`.
- This slice claims only a larger deterministic `date3-1.7` generated timestamp batch and does not repeat metadata-only suite rows, WordPress-shaped APIs, JSON/PRAGMA/VFS/WAL/B-tree surfaces, or source-neutral cleanup.

Dependency closure:

- No new support component is needed. The tests reuse native `SQLiteCoreScalarFunction` date/time dispatch and `SQLiteSelectSql` row filtering.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicUnixepochBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicUnixepochBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
