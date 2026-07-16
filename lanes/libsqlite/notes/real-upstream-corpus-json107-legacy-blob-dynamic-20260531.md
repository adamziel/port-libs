# real-upstream-corpus-json107-legacy-blob-dynamic-20260531

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T052627Z-0`

Accepted base: `e6f2f82c55065569a50189235fcdfbfbb9091c15`

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`

Ported sections:

- `json107-1.1`, `json107-1.1.1`, `json107-1.1.2`, `json107-1.1.4`, `json107-1.1.8`: `json_valid()` over JSON text stored in a BLOB, including strict text, JSON5 text, superficial JSONB, and strict JSONB flags.
- `json107-1.2.1`, `json107-1.2.2`, `json107-1.2.3`: legacy text-BLOB extraction through `->`, `->>`, and `json_extract()`.
- `json107-1.3` through `json107-1.8`: `json_insert()`, `json_remove()`, `json_set()`, `json_replace()`, `json_type()`, and `json()` over text-BLOB JSON.
- `json107-2.1`: `json_tree()` atom rows over text-BLOB JSON.

Focused evidence:

- First run exposed a test fixture mistake in duplicate `json_tree` atom keys: `160` failures where `items[1].key` overwrote `items[0].key` in the assertion map.
- Fixed the test to assert repeated object member names by `fullkey`.
- Passing command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson107LegacyBlobDynamicTest.php`
- Result: `1 test files, 4644 assertions, 0 failures`
- PASS-line growth: `1281` focused TestRunner PASS cases in one new real-upstream JSON107 test file.

Dependency closure: no new support component is needed. The existing native PHP JSON, JSONB, `SQLiteBlobValue`, `SQLiteSelectExpression`, and JSON table/tree helpers are reused.

Non-overlap: this does not touch accepted JSON table cursor/source wiring, hidden/visible constraints, JSON109 array-insert error matrix, JSON106 invariants, JSON104 merge-patch, JSON501/JSON502 escaped/control-character stress, JSON aggregates/windows, or metadata-only suite evidence.
