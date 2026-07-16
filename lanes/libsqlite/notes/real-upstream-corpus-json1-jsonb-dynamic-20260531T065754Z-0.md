# real-upstream-corpus-json1-jsonb-dynamic-20260531T065754Z-0

Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`

Ported sections:

- `json101-1.1`: `json_array()` text values are quoted, while JSON and JSONB subtype values are inserted structurally.
- `json101-2.1`, `json101-2.2.2`, `json101-2.2.3`: `json_object()` / `jsonb_object()` preserve plain SQL text as strings while inserting JSON and JSONB constructor values structurally.
- `json101-3.1`, `json101-3.2`: `json_replace()` text replacement versus JSON/JSONB structural replacement.
- `json101-3.3`, `json101-3.4`: `json_set()` text value type versus JSON/JSONB object subtype insertion.

Focused test:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorSubtypeDynamicTest.php`.
- The test creates 1,000 deterministic upstream-shaped rows over constructor, mutation, extraction, type inspection, and JSONB canonical parity behavior.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ConstructorSubtypeDynamicTest.php` passed with `1 test files, 29003 assertions, 0 failures` and 1,001 PASS lines.

Non-overlap:

- This does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSON102 path/operator/mutation batches, JSON103 aggregate/window behavior, JSON104 patch batches, JSON105 reverse path mutation, JSON106/108 invariant corpus, JSON107 BLOB text behavior, JSON109 array insert behavior, JSON501/502 escaped/JSON5 corpus, or jsonb01 malformed/remove batches.
- The bounded surface is upstream `json101.test` constructor and mutation value-subtype behavior: plain text remains quoted, JSON subtype values are inserted structurally, and JSONB constructor/mutation paths canonicalize to equivalent JSON.

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteJsonConstructor`, `SQLiteJsonMutation`, `SQLiteJsonExtract`, `SQLiteJsonInspection`, `SQLiteJsonCanonical`, and `SQLiteJsonB` behavior.
