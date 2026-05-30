# real-upstream-corpus-json1-jsonb-dynamic-20260530T195354Z-0

Added focused real-upstream JSON1/JSONB dynamic corpus coverage from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`

Upstream sections ported:

- `json101-5.3`: `json_tree()` `fullkey` equals `path` plus formatted `key`.
- `json101-5.4`: `json_each()` `fullkey` formatting parity for immediate rows.
- `json101-5.5` and `json101-5.6`: `json_each.json` / `json_tree.json` echo the input JSON source.
- `json101-5.7` and `json101-5.8`: scalar rows keep `value == atom` while array/object rows keep `atom` as SQL null.

Focused behavior added:

- New test file: `lanes/libsqlite/tests/SQLiteRealUpstreamJson101TreeInvariantDynamicBulkTest.php`.
- The file creates 220 deterministic generic JSON documents derived from the upstream `j2` examples and runs text plus JSONB row-generator invariants through `SQLiteJsonTree`, `SQLiteJsonEach`, `SQLiteJsonExtract`, and `SQLiteJsonB`.
- Focused result: 661 distinct TestRunner PASS cases and 127,235 behavior assertions.

Non-overlap:

- This does not add metadata-only rows, generated fake upstream script ids, domain-shaped APIs, JSON table planner/source/cursor admission, hidden/visible constraint pushdown, JSON104 merge-patch, JSON105 dynamic path mutation, JSON106 invariants, JSON107 BLOB compatibility, JSON108 pretty output, JSON109 array insert, JSON501/502 JSON5 escape coverage, or JSONB remove coverage.
- The new assertions target native row-generator column invariants from real `json101.test` sections 5.3 through 5.8.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101TreeInvariantDynamicBulkTest.php`
  - `1 test files, 127235 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses existing native PHP JSONB encoding, JSON tree/each row generation, path extraction, and canonicalization helpers.
