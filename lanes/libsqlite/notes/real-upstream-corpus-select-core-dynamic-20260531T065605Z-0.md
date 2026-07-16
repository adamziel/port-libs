# real-upstream-corpus-select-core-dynamic-20260531T065605Z-0

Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`.

Added a real upstream SELECT core dynamic batch from hydrated SQLite upstream
`/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`.

Owned upstream scenarios:

- `select2-4.1`: `SELECT * FROM aa, bb WHERE max(a,b)>2`
- `select2-4.2`: `SELECT * FROM aa CROSS JOIN bb WHERE b`
- `select2-4.3`: `SELECT * FROM aa CROSS JOIN bb WHERE NOT b`
- `select2-4.4`: `SELECT * FROM aa, bb WHERE min(a,b)`
- `select2-4.5`: `SELECT * FROM aa, bb WHERE NOT min(a,b)`
- `select2-4.6`: `SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 1 END`
- `select2-4.7`: `SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 0 ELSE 1 END`

The PHP port uses generic `left_rows` and `right_rows` fixtures and expands
those seven upstream boolean cross-join predicates across 150 deterministic
input seeds. This yields 1,050 dynamic behavior cases plus one upstream-source
citation case.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicBooleanJoinsTest.php`
- Result: `1 test files, 4209 assertions, 0 failures`
- PASS-line growth: 1,051 focused TestRunner PASS cases

Non-overlap:

- Does not repeat existing `select3.test` aggregate/group coverage,
  `select8.test` grouped LIMIT/OFFSET coverage, or the prior
  `select5.test`/`select6.test` derived aggregate batch.
- Does not add generated fake upstream script ids or metadata-only rows.

Dependency closure:

- No new support component is needed. The batch reuses the existing
  `SQLiteSelectSql` parser/executor and the hydrated upstream SQLite corpus.
