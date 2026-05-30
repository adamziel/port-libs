# Real Upstream JSON1/JSONB Dynamic Expansion

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T194405Z-0`

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`

Added `SQLiteRealUpstreamJson1JsonbDynamicExpansionTest.php` with focused behavior coverage from the hydrated upstream SQLite corpus:

- `jsonb01.test`: JSONB remove path behavior for object members, array indexes, append token no-ops, and reverse indexes.
- `json105.test`: `[#]` and `[#-N]` path extraction, removal, insert, set, and replace behavior.
- `json109.test`: `json_array_insert()` current-index insertion and JSONB parity.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicExpansionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicExpansionTest.php`
- Result: `1 test files, 7425 assertions, 0 failures`, with `1465` focused PASS lines.

Non-overlap:

- Does not add production source, metadata-only admission rows, fake script IDs, domain-named APIs, or new source defaults.
- Avoids accepted JSON102/103/104/106/501/502 coverage and keeps this slice to JSONB remove, json105 reverse path behavior, and json109 array insert parity.

Dependency closure:

- No new support component is needed. Existing native JSON, JSONB, JSON5 parser, mutation, remove, extract, and array-insert helpers are reused.
