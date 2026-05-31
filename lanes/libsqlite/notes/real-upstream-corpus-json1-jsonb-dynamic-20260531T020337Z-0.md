# real-upstream-corpus-json1-jsonb-dynamic-20260531T020337Z-0

Base accepted HEAD: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Sections ported: `json102-400`, `json102-410..430`, `json102-440..500`, and `json102-510..600`.

Added focused PHP coverage:

- `SQLiteRealUpstreamJson102JsonbMutationTailDynamicTest.php`
- 711 TestRunner PASS cases
- 6503 behavior assertions
- Coverage cluster: JSON/JSONB `json_set` value-subtype handling, `json_object`/`jsonb_object` and array value insertion parity, ordered `json_remove` path semantics, out-of-range remove no-ops including large indexes, and `json_type` parity across text JSON, JSONB, and SELECT expression dispatch.

Non-overlap:

- Does not repeat accepted JSON table cursor/source/hidden/visible-constraint work.
- Does not repeat prior JSON101 constructor, JSON103 aggregate/window, JSON104 patch, JSON105 reverse-path-only, JSON106 invariant, JSON107 BLOB text, JSON108 pretty, JSON109 array-insert, JSON501/502 JSON5/path, or jsonb01 remove-only dynamic files.
- Adds no WordPress/wp source text or domain-specific API.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP JSON canonicalization, JSONB, mutation, remove, inspection, and SELECT expression dispatch helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102JsonbMutationTailDynamicTest.php`
- Result: `1 test files, 6503 assertions, 0 failures`

Root harness:

- Not run - isolated micro-slice.
