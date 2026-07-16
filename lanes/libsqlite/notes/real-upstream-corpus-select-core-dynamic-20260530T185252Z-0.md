# real-upstream-corpus-select-core-dynamic-20260530T185252Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`

Ported upstream scenarios:

- `selectB-1.2` and `selectB-1.3`: compound subquery flattening, including `SELECT * FROM (...) ORDER BY 1`.
- `selectB-1.4` through `selectB-1.6`: outer predicate pushdown over compound subqueries, including an existing right-arm predicate.
- `selectB-1.9` through `selectB-1.11`: three-arm `UNION ALL` compound subqueries with ordering and predicate filtering.
- `selectB-3.1` through `selectB-3.4`: distinct/grouped compound subquery rows and host-table joins.

Implementation:

- `SQLiteSelectSql` now annotates wildcard projection terms with source columns and resolves `ORDER BY` ordinal terms through expanded wildcard columns. This fixes the shared executor blocker exposed by upstream `selectB-1.3` where `SELECT * FROM (compound) ORDER BY 1` previously raised `SQLite SELECT SQL ORDER BY wildcard ordinal is not supported`.

Focused coverage:

- Added `SQLiteRealUpstreamSelectBFlattenDynamicTest.php`.
- The file generates 4,420 distinct TestRunner PASS cases from the selected real upstream `selectB.test` scenarios by checking full-result transform parity and 21x21 dynamic `LIMIT`/`OFFSET` windows for each selected derived/flattened query pair.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBFlattenDynamicTest.php` passed `1 test files / 17680 assertions / 0 failures`.

Non-overlap:

- This does not repeat accepted select1-select9/selectA coverage, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, WAL/VFS/B-tree clusters, or metadata-only runner admission.
- Expected dashboard classification: PASS-line growth only. Mapped denominator remains unchanged because `selectB.test` already exists in the hydrated upstream inventory and runner-map evidence.

Dependency closure:

- No new support component is needed. The slice reuses lane-local `SQLiteSelectSql`, compound SELECT execution, derived-table source planning, wildcard projection, predicate filtering, grouping, ordering, and LIMIT/OFFSET result trimming.
