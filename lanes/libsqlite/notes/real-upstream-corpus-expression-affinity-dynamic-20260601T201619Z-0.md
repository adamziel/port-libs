# real-upstream-corpus-expression-affinity-dynamic-20260601T201619Z-0

Status: ready for isolated lane handoff.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/instr.test`
- Covered scenarios: `instr-1.1` through `instr-1.57` for text, numeric, UTF-8, BLOB, NULL, empty needle, and mixed text/BLOB `instr()` behavior.

Behavior:

- Fixed `SQLiteCoreScalarFunction::instr()` text-mode search so an invalid BLOB-as-text needle such as `X'a4'` does not match inside a valid UTF-8 character. Upstream `instr.test instr-1.57.2` expects `instr('xä€y',x'a4')` to return `0`, while true BLOB/BLOB search keeps byte positions.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicInstr20260601T201619ZTest.php`, an oracle-backed matrix with 1,210 dynamic `instr()` expressions over direct results, `coalesce()`, `nullif()`, numeric addition, and truth predicates. Each case compares `quote()`, `typeof()`, and NULL propagation against local `sqlite3`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInstr20260601T201619ZTest.php`
- Result: `1 test files, 6060 assertions, 0 failures`
- PASS growth: 1,212 TestRunner PASS cases.
- Status delta: `phpPass` `6247535 -> 6248747`; mapped coverage unchanged at `1589 / 1589`; broad release parity still records 16 known failures.

Non-overlap:

- This slice owns upstream `instr.test instr-1.*` expression-affinity behavior only.
- It avoids accepted substr, math, CAST, LIKE/GLOB, CASE/iif, JSON, WAL, VFS, B-tree, PRAGMA, UPSERT, and row-value LIMIT/OFFSET batches.

Dependency closure:

- No new support component is needed; the patch reuses `SQLiteSelectSql` expression dispatch and `SQLiteCoreScalarFunction` scalar behavior.
