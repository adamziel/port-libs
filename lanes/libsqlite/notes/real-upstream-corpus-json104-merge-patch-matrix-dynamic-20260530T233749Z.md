# real-upstream-corpus-json104-merge-patch-matrix-dynamic-20260530T233749Z

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T233749Z-0`

Base accepted HEAD: `e26da88382a9c31477121cff98ca70bfba38b5f3`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`

Ported behavior cluster:

- `json104-100`: RFC-7396 object member replacement and nested null deletion.
- `json104-110`: RFC-7396 object merge with nested member removal, array replacement, preserved content, and added phone number.
- `json104-210`: object patch over array target with null-member omission.
- `json104-221` and `json104-222`: nested nulls inside arrays are preserved instead of treated as object-member deletion.
- `json104-306`: recursive object patch replaces one nested member and deletes another.
- `json104-308` and `json104-309`: non-object patches replace the target document.
- `json104-312`: null-valued target members survive unrelated object patches.
- `json104-320`: duplicate-key/last-write shape represented as the effective final patch member.

Focused delta:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson104MergePatchMatrixDynamicTest.php`.
- The file emits `1004` focused TestRunner PASS cases and `15253` behavior assertions.
- The matrix varies each upstream-derived seed through deterministic wrapped documents, checking `json_patch()` text output, `jsonb_patch()` parity, canonical output, validity, deletion, preserved members, added members, array replacement, nested null handling, and path extraction from both text and JSONB results.

Non-overlap:

- This is not metadata admission and does not add fabricated upstream script ids.
- It does not repeat JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window behavior, JSON105 reverse-index mutation, JSON106/108 invariant bulk, JSON109 array insert, JSON501/502 JSON5/escaped-path coverage, or JSONB remove-path coverage.
- It expands real upstream `json104.test` merge-patch behavior beyond the existing smaller RFC seed coverage by adding a high-yield deterministic matrix with distinct wrapped patch documents.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson104MergePatchMatrixDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson104MergePatchMatrixDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104MergePatchMatrixDynamicTest.php
1 test files, 15253 assertions, 0 failures
```

Dependency closure:

- No new support component is needed. This reuses existing native PHP JSON patch, JSONB, canonicalization, validity, and extraction helpers.
