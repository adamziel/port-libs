# real-upstream-corpus-json1-jsonb-dynamic-20260531T103918Z-0

- Base accepted HEAD: `f9d9e6312c63dfc0751eedbcf238e9e6c2d6e7da`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`.
- Upstream sections: `json101-15.100`, `json101-15.110`, `json101-15.120`, and `json101-15.130`.
- Behavior: parser-level `SQLiteSelectSql` now keeps single-source rows qualified when the SELECT list requires `alias.*`, so `JSON_EACH(...) AS xyz` and `(JSON_EACH(...)) AS xyz` project the same rows as the upstream plain `*` source shapes.
- Focused PASS movement: `+1002` TestRunner PASS cases from the new dynamic corpus test, with `1006` focused assertions.
- Mapped denominator movement: unchanged at `1589 / 1589`; this is PASS-line growth over an already mapped upstream JSON101 file.
- Non-overlap: this patch does not repeat the accepted JSON102 guarded mixed scalar/JSON phone rows, JSON table cursor/source wiring, JSON hidden or visible constraint pushdown, JSON104 merge-patch matrix, JSON105/106 mutation paths, JSON109 select-SQL behavior, or JSONB remove coverage.
- Dependency closure: no new support component is needed; this reuses `SQLiteSelectSql` source planning, existing JSON table source dispatch, and wildcard projection.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101AliasStarDynamic20260531Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson101AliasStarDynamic20260531Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101AliasStarDynamic20260531Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 1006 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS libsqlite source has no WordPress-named text
PASS libsqlite filenames have no WordPress-specific names
PASS libsqlite php declarations have no WordPress-specific class or method names

1 test files, 3 assertions, 0 failures

php -r '$data = file_get_contents("lanes/libsqlite/lane-status.json"); json_decode($data, true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . "\n"); exit(1); } echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/libsqlite
no output
```

Root harness: not run - isolated micro-slice.
