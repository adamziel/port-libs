# Real Upstream Corpus JSON102 Tree Projection Dynamic 2026-06-01

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260601T033115Z-0`

Base: `639880c48c54d40c3ed0188758af6aee8d8d2712`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `json102-1110`: `SELECT big.rowid, fullkey, value FROM big, json_tree(big.json) ...`
- `json102-1110b`: `SELECT big.rowid, fullkey, value FROM big, json_tree(jsonb(big.json)) ...`
- `json102-1120`: `SELECT big.rowid, fullkey, atom FROM big, json_tree(big.json) WHERE atom IS NOT NULL ...`

Patch summary:

- Added `SQLiteRealUpstreamJson102TreeProjectionDynamic20260601Test.php`.
- Covers 1000 dynamic host-table JSON fixtures using parser-level `SQLiteSelectSql` cross joins against `json_tree()`.
- Verifies text JSON, `jsonb(big.json)`, and stored JSONB traversal project the same upstream-style `rowid`, `fullkey`, `value`, and `atom` leaf rows.
- Preserves the upstream unqualified JSON table projections for `fullkey`, `value`, and `atom`, plus ordered `+big.rowid, +json_tree.id` traversal.

Focused evidence:

- Red-first: the initial local run failed only on an over-strict fixture-size guard before the upstream projection assertions executed.
- Passing command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102TreeProjectionDynamic20260601Test.php`
- Result: `1 test files, 11007 assertions, 0 failures`
- PASS growth: `+1002` focused TestRunner cases.

Non-overlap:

- Existing JSON102 dynamic coverage already checks direct `SQLiteJsonTree` scalar parity and parser-level DISTINCT uuid search for `json102-1130..1132`.
- This slice targets parser-level row projection/value-vs-atom parity for upstream `json102-1110`, `json102-1110b`, and `json102-1120`; it does not add another JSON table cursor/source/hidden/visible-constraint batch.

Dependency closure:

- No new support component is required.
- The tests reuse existing `SQLiteSelectSql`, dynamic JSON table source wiring, `SQLiteJsonTree`, and `SQLiteJsonB` helpers.
