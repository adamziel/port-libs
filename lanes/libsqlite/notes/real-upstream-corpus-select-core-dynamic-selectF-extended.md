# real-upstream-corpus-select-core-dynamic selectF extended

Base accepted HEAD: `1e28a5dbe5f8813a907a64ec2d403f8339418de7`

Micro-slice: `real-upstream-corpus-select-core-dynamic-20260530T234406Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test`
- Scenario: `selectF-2`, compound `SELECT * FROM t2 UNION ALL SELECT * FROM t1 WHERE a<5 ORDER BY 2, 1`
- Behavior: preserve compound SELECT source-register values across final ordering, matching upstream's OP_Copy versus OP_SCopy regression.

Lane-local implementation:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectFUnionCopyExtendedDynamicTest.php`.
- The file contributes 1001 TestRunner PASS cases:
  - 1 source-citation test that validates hydrated upstream `selectF.test` and the selectF-2 SQL shape.
  - 1000 seed-specific dynamic behavior tests through `SQLiteSelectSql::execute()`.
- Each seed varies row values, ordering bucket, NULL second-key placement, first-key tie breaking, and whether the filtered right arm contributes one or two rows.
- This is additive to the older accepted `SQLiteRealUpstreamSelectESelectFDynamicTest.php` coverage; it uses the same upstream scenario family but a new extended dynamic seed matrix and a distinct focused test file.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectFUnionCopyExtendedDynamicTest.php`
- Result: `1 test files, 3006 assertions, 0 failures`
- PASS-line movement: `+1001`, raising lane-local selected throughput from `1170170` to `1171171` pass / `0` fail.

Dependency closure:

- No new support component is needed.
- Existing `SQLiteSelectSql` compound SELECT, `UNION ALL`, `WHERE`, final `ORDER BY`, and NULL ordering behavior is reused.
- Full release/all runner parity remains outside this isolated micro-slice.
