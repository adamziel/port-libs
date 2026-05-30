# Real Upstream Corpus: types2 Affinity Dynamic Bulk

- Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T181224Z-0`
- Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- Upstream sections: `types2-2.*`, `types2-3.*`, and `types2-4.*`
- Added focused PHP file: `lanes/libsqlite/tests/SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php`

This shard ports INTEGER, NUMERIC, and no-affinity comparison behavior from
SQLite `types2.test` into row-array SELECT execution. It uses the real
`sqlite3` CLI as the oracle and checks 200 upstream-derived predicates through
five contexts: selected rowids, negated selected rowids, projected truth vector,
`IS 1`, and `IS NOT 1`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php`
- Result: `1 test files, 1004 assertions, 0 failures`
- Counted PASS lines: `1001`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php`: no syntax errors
- `git diff --check -- lanes/libsqlite`: passed

Non-overlap:

This is not the existing `e_expr.test` precedence shard and does not add
metadata-only admission rows. It exercises `types2.test` manifest-type and
affinity comparison behavior against executable SELECT/WHERE paths.

Dependency closure:

No new support component is needed. The remaining excluded upstream surface is
TEXT-affinity declared-column comparison, which needs schema-affinity metadata
propagated into the bounded row-array SELECT executor before it can be admitted
honestly.
