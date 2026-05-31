# real-upstream-corpus-upsert-returning-dynamic-20260531T102021Z-0

Base accepted HEAD: `334e4120b9e72c6876e51705851ef70fc2462655`

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- Sections `24.0` through `24.3`: create `fts5(c)`, perform a peer connection write to `t2`, then run `INSERT INTO ft VALUES('hello world') RETURNING *` and receive `hello world`.

Implemented behavior:
- Added generic `SQLiteReturningVirtualTablePlan::insertFts5ReturningAfterPeerWrite()`.
- Added `SQLiteRealUpstreamReturningFts5VirtualDynamicTest.php` with 1000 dynamic FTS5 RETURNING peer-write cases plus malformed-input, source-citation, and dependency-closure guards.
- The planner keeps the upstream distinction that virtual-table insert admission succeeds, RETURNING is evaluated, and the inserted FTS5 content row is emitted even after a peer schema/data write.

Non-overlap:
- This does not repeat the accepted `returning1.test` virtual-table handoff for `9.1` read-only pragma `UPDATE ... RETURNING` or `13.1` RTREE scalar-subquery RETURNING.
- It also avoids the accepted UPSERT multi-arm, omitted target, partial-index, trigger histogram, repeated-fooval, writable-schema, correlated-subquery, and temp-trigger RETURNING slices.

Focused verification:
- `php -l lanes/libsqlite/src/SQLiteReturningVirtualTablePlan.php`: pass
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningFts5VirtualDynamicTest.php`: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningFts5VirtualDynamicTest.php`: `1 test files, 12004 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningFts5VirtualDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningVirtualTableDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `3 test files, 23015 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`: pass

Expected lane movement:
- Focused PASS growth: `+1004` TestRunner PASS cases.
- Focused behavior assertions: `12004`.
- Mapped coverage: unchanged; this is additional behavior coverage under already mapped `returning1.test`.

Dependency closure:
- No new support component needed; this reuses the generic virtual-table RETURNING planner for FTS5 inserted-row streams after peer writes.
