# real-upstream-corpus-json1-jsonb-dynamic-20260531T050729Z-0

Base accepted HEAD: `7174979f2808c9ccf08c3331545660695c77e192`.

Added `SQLiteRealUpstreamJson104JsonbMergePatchYieldDynamicTest.php`, a
real-upstream JSON104 merge-patch yield batch backed by
`/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`.

Covered upstream scenarios:

- `json104-100` nested member deletion.
- `json104-110` RFC 7396 object merge behavior.
- `json104-210` array target replacement by object patch.
- `json104-304` scalar patch replaces array member.
- `json104-305` array patch replaces scalar member.
- `json104-310` scalar/null patch replacement.
- `json104-312` null target member preservation while appending.

Focused growth:

- `1001` distinct TestRunner PASS cases.
- `17003` focused behavior assertions.
- Text `json_patch` and JSONB `jsonb_patch` parity over 1000 generated
  application documents.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson104JsonbMergePatchYieldDynamicTest.php`
  passed.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104JsonbMergePatchYieldDynamicTest.php`
  passed: `1 test files, 17003 assertions, 0 failures`.

Dependency closure: no new support component is needed; this reuses existing
native JSON canonicalization, JSONB encoding/decoding, JSON merge-patch,
inspection, extraction, tree, and validity helpers.

Non-overlap: this does not edit production source and does not repeat recent
JSON table cursor/source/hidden/visible constraint work, JSON501/502 escaped
path stress, JSON106/108 pretty invariants, JSONB remove path parity, or
JSON103 aggregate/window coverage. The batch focuses on JSON104 RFC 7396
merge-patch object/null/array replacement parity across text JSON and JSONB.
