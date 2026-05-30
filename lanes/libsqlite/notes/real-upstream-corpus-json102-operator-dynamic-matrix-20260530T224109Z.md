# real-upstream-corpus-json102-operator-dynamic-matrix-20260530T224109Z

- Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`.
- Covered upstream scenarios: `json102-1600`, `json102-1610`, `json102-1620`, and `json102-1800` through `json102-1831`.
- Added focused PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorDynamicMatrixTest.php`.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorDynamicMatrixTest.php` passed with `1 test files, 5103 assertions, 0 failures`.
- PASS-line movement: `+1101` focused TestRunner PASS cases if accepted.
- Mapped denominator movement: none; mapped inventory is already `1589 / 1589`.
- Non-overlap: this covers JSON102 `->` / `->>` operator result typing and numeric-looking RHS ambiguity, not JSON105/JSON109 mutation, JSONB remove, JSON table cursor/source/constraint pushdown, or JSON pretty invariant coverage.
- Dependency closure: no new support component needed; this reuses native `SQLiteSelectExpression`, `SQLiteJsonExtract`, `SQLiteJsonPath`, and `SQLiteJsonB`.
