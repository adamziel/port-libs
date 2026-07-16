# real-upstream-corpus-select-core-dynamic-20260530T212546Z-0

Added `SQLiteRealUpstreamSelectHOrderedOmitUnusedDynamicTest.php` and fixed the shared compound `ORDER BY` matcher exposed by upstream `selectH.test`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- `selectH-2.1` / `selectH-2.2`: unused `counter(1)` projection columns in compound arms must be omitted, while the compound subquery still honors `ORDER BY b` where `b` is an explicit projection after `*`.

Behavior change:

- `SQLiteSelectSql::compoundOrderByMatchedColumn()` now walks expanded wildcard projection columns with the correct output ordinal instead of mapping a post-wildcard alias by raw select-term index.
- Before the fix, `ORDER BY b` in the `selectH-2.1` shape bound to `c1`, so the derived compound returned `[15, 16]` instead of upstream SQLite's `[16, 15]`.
- After the fix, the ordered derived compound prunes unused `counter(1)` and orders by the explicit `b` projection.

Focused PHP coverage:

- New test file: `lanes/libsqlite/tests/SQLiteRealUpstreamSelectHOrderedOmitUnusedDynamicTest.php`
- 1,001 distinct TestRunner PASS cases.
- 5,005 focused behavior assertions.
- The dynamic batch varies selected `a` columns and high/low `b` sort-key columns over 1,000 generic single-row table shapes while preserving the upstream `counter(1)`, wildcard, `UNION ALL`, derived-table, and `ORDER BY b` structure.

Non-overlap:

- This slice owns the previously noted `selectH-2.1` ordered compound follow-up.
- It does not repeat accepted `selectH-1.2` omit-unused filtering, `selectH-5` count-literal coverage, accepted `select1` through `selectG` batches, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, WAL/VFS/B-tree surfaces, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectH.test` is already present in the hydrated upstream runner-map evidence.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHOrderedOmitUnusedDynamicTest.php`
  - Result: `1 test files, 5005 assertions, 0 failures`
  - PASS lines: 1,001

Dependency closure:

- No new support component is needed. The patch reuses existing `SQLiteSelectSql`, compound SELECT, wildcard projection, derived table, and omit-unused counter pruning behavior.
