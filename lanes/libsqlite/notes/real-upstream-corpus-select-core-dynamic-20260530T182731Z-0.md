# real-upstream-corpus-select-core-dynamic-20260530T182731Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`.
- Ported scenarios: `selectA-2.1`, `selectA-2.1.1`, `selectA-2.1.2`, `selectA-2.2` through `selectA-2.6`, `selectA-2.10` through `selectA-2.16`, and `selectA-2.20`.
- Focused coverage: `SQLiteRealUpstreamSelectACompoundOrderDynamicTest.php` adds 1,952 distinct TestRunner PASS cases and 7,808 focused assertions over compound `UNION ALL` merge ordering, qualified projection/order resolution, `NOCASE`/`BINARY` explicit collation terms, reversed compound arms, and bounded `LIMIT`/`OFFSET` slices.
- Behavior fix: `SQLiteSelectExpression` now resolves qualified projection names such as `t1.a` against the unqualified row field when the current row source is unambiguous, and `SQLiteSelectSql` compound `ORDER BY` matching accepts qualified terms whose suffix matches the compound output column.
- Known excluded upstream follow-up: `selectA-2.7`, `selectA-2.8`, and `selectA-2.9` require table-declared collation propagation for implicit `c COLLATE NOCASE` compound ordering. They are not hidden as passing coverage in this slice.
- Expected dashboard classification: PASS-line growth only; mapped denominator remains unchanged because `selectA.test` is already present in the hydrated upstream inventory.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectACompoundOrderDynamicTest.php` passed: `1 test files, 7808 assertions, 0 failures`.
- Dependency closure: no new support component needed; this reuses lane-local `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteSelectCompound`, `SQLiteSelectResult`, and `SQLiteBlobValue`.
- Non-overlap: this does not repeat prior select1-select9 dynamic coverage, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, WAL/VFS/B-tree clusters, or metadata admission rows. It targets selectA compound merge ordering and the qualified-column behavior exposed by upstream ticket #3314.
