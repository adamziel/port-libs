# real-upstream-corpus-json1-jsonb-dynamic-20260530T164557Z-0

- Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T164557Z-0`
- Base accepted HEAD: `77aaee93e1232164eda546b44d6f0e2ddd146261`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`
  - Existing adjacent focused file also retains prior `json101.test`, `json102.test`, `json105.test`, and `jsonb01.test` coverage.

## Behavior Ported

Extended `SQLiteRealUpstreamJson1JsonbDynamicTest.php` with real `json104.test` behavior for RFC-7396 JSON merge patch:

- `json104-100` through `json104-110`: RFC examples, JSON5 object labels, nested object merge, and null-member deletion.
- `json104-200` through `json104-222`: object patches over non-object targets, nested empty-object preservation, and null values inside arrays.
- `json104-300` through `json104-320`: scalar/array/object replacement, SQL NULL propagation, duplicate patch keys, and quoted-path extract/update behavior from the table mutation tail.
- Each merge-patch case is asserted through `json_patch`, `jsonb_patch`, and `json_patch` over JSONB inputs where applicable.

The new upstream cases exposed and fixed a real behavior gap: empty JSON object results decoded from JSONB were canonicalized as arrays (`[]`). `SQLiteJsonB` now preserves empty object type when decoding object payloads, so RFC-7396 member deletion returns `{}` and nested empty objects stay objects.

## Focused Evidence

- First focused run before the behavior fix failed with 13 failures on `json104-200`, `json104-220`, `json104-302`, `json104-314`, and one quoted-path test harness call.
- After fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  - Result: `1 test files, 283 assertions, 0 failures`
- Focused assertion/PASS growth: `+83` over the prior 200-assertion focused file shape.

## Non-Overlap

This slice is non-overlapping with the previous real JSON dynamic batch, which covered `json101.test`, `json102.test`, `json105.test`, and `jsonb01.test`. The new behavior is specifically `json104.test` RFC-7396 merge patch and quoted path table-update behavior.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON5 parsing, JSONB encode/decode, JSON canonicalization, JSON path handling, and JSON mutation/extract helpers already present under `lanes/libsqlite/src`.
