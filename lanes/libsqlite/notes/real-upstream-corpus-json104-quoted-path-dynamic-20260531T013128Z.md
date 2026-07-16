# real-upstream-corpus-json104-quoted-path-dynamic-20260531T013128Z

- Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T013128Z-0`
- Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`
- Ported sections: `json104-401`, `json104-402`, `json104-403`, `json104-404`, `json104-405`, plus duplicate-member patch ordering from `json104-320`.

Behavior covered:

- Dynamic quoted and unquoted object-member path equivalence after `json_insert()` and `json_set()`.
- Text and JSONB mutation parity for the same quoted-path update sequence.
- `json_patch()` object-member overwrite behavior before another quoted-path mutation.
- `json_extract()` parity for quoted and unquoted member lookups after each mutation.

Focused evidence:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson104QuotedPathDynamicTest.php`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104QuotedPathDynamicTest.php`
- Result: `1 test files, 4502 assertions, 0 failures`.
- Focused PASS-line growth: `1001` real upstream-derived TestRunner PASS cases.

Non-overlap:

This slice does not repeat JSON table cursor/source/hidden/visible constraint work, JSON101 constructor coverage, JSON102 extract/operator coverage, JSON103 aggregate/window coverage, existing JSON104 merge-patch matrix coverage, JSON105 reverse-index mutation, JSON106/108 invariant sweeps, JSON107 legacy BLOB behavior, JSON109 array insert behavior, JSON501/502 JSON5 lexical/path behavior, or JSONB remove parity. It owns the remaining `json104.test` object-update path rows around quoted member equivalence.

Dependency closure:

No new support component is needed. The test reuses existing native JSON helpers: `SQLiteJsonMutation`, `SQLiteJsonExtract`, `SQLiteJsonPatch`, `SQLiteJsonCanonical`, and `SQLiteJsonB`.
