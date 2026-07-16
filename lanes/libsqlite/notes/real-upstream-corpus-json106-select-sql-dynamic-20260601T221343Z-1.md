# Real Upstream JSON106 SELECT SQL Dynamic Slice

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260601T221343Z-1`

Base accepted HEAD: `61d6474675e62bc503d755e05f4aa9303f52ded5`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`
- Covered loop sections: `$ii.1`, `$ii.2`, `$ii.3`, `$ii.7`, `$ii.8`, and `$ii.9`
- Ported upstream loop ordinals: `1001..2000`

Behavior added:

- Adds parser-level `SQLiteSelectSql` corpus coverage for JSON106 random JSON/JSON5 invariants.
- Exercises `json_valid(j0)`, `json_valid(j5,2)`, `json_tree(j0)`, `json_tree(j5)`, `WHERE rt.type NOT IN ('object','array')`, `j0->>rt.fullkey`, `j5->>rt.fullkey`, `json_patch(j0,j5)->>rt.fullkey`, `json(json_pretty(j0))`, and `json(json_pretty(j5))` through SELECT execution.
- Keeps the existing lower-level JSON106 helper coverage intact; this batch proves the same upstream invariant class through SELECT/FROM/WHERE/ORDER dispatch and JSON table source execution.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson106SelectSqlDynamic20260601Test.php`
- Result: `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson106SelectSqlDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson106SelectSqlDynamic20260601Test.php`
- Result: `1 test files, 120010 assertions, 0 failures`
- PASS-line count: `1001` focused cases

Non-overlap:

- Avoids existing JSON106 direct-helper invariant files: `SQLiteRealUpstreamJson106InvariantDynamicThousandTest.php`, `SQLiteRealUpstreamJson106InvariantBulkDynamicTest.php`, `SQLiteRealUpstreamJson106InvariantDeterministicDynamicTest.php`, and `SQLiteRealUpstreamJson106Json108InvariantLargeCorpusTest.php`.
- Avoids accepted JSON101 NULL SELECT SQL, JSON102 mutation/operator/subtype, JSON103 aggregate SELECT SQL/window, JSON104 patch, JSON105 index, JSON107 legacy BLOB, JSON108 pretty, JSON109 array insert/atomic error, JSON501/502, and JSONB malformed corpus slices.
- This slice specifically covers hydrated upstream `json106.test` loop invariants through parser-level SELECT SQL execution over `json_tree()` sources.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, JSON table source wiring, JSON scalar/operator dispatch, JSON5 validation, JSON patching, JSON pretty canonicalization, and the existing `TestRunner`.

Status delta:

- `phpPass` moves `6280622 -> 6281623` from `1001` focused PASS cases.
- Focused assertion count is `120010`.
- `phpFail` remains `16`; broad full-lane/release parity was not run in this isolated micro-slice.
