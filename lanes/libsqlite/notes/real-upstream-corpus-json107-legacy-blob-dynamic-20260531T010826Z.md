# real-upstream-corpus-json1-jsonb-dynamic-20260531T010826Z-0

## Scope

- Added a real upstream JSON dynamic corpus slice for `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`.
- Ported the legacy SQLite behavior that a BLOB containing valid UTF-8 JSON text is accepted by JSON functions as text JSON, while JSONB-only flags still distinguish real JSONB blobs.
- Covered upstream json107 sections:
  - `json107-1.1` / `1.1.1` / `1.1.2` / `1.1.4` / `1.1.8`: `json_valid()` flag behavior for text BLOBs versus JSONB BLOBs.
  - `json107-1.2.1` through `1.2.3`: extraction/operator-equivalent path reads from text BLOB JSON.
  - `json107-1.3` through `1.8`: `json_insert`, `json_remove`, `json_set`, `json_replace`, `json_type`, and `json()` over text BLOB JSON.
  - `json107-2.1`: `json_tree()` scalar atom visibility over text BLOB JSON.

## Evidence

- Focused test command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson107LegacyBlobDynamicCorpusTest.php`
- Result:
  - `1 test files, 7005 assertions, 0 failures`
  - `1001` focused PASS cases.
- Non-overlap:
  - This does not repeat existing JSON501/502 JSON5 path escape coverage, JSON109 array insert coverage, JSON101 constructor coverage, JSON104 merge patch coverage, or JSON106/108 invariant/pretty coverage.
  - The slice is specifically json107 legacy text-BLOB behavior and JSONB flag contrast.

## Dependency Closure

- No new support component is needed. The slice reuses existing bounded native PHP components: `SQLiteBlobValue`, `SQLiteJsonValidity`, `SQLiteJsonExtract`, `SQLiteJsonInspection`, `SQLiteJsonMutation`, `SQLiteJsonRemove`, `SQLiteJsonCanonical`, `SQLiteJsonTree`, and `SQLiteJsonB`.
