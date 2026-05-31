# real-upstream-corpus-json109-array-insert-error-matrix-20260531

Base accepted HEAD: `892244279ab2272eec684ce3477ab002d81ab0b4`

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`

Owned upstream sections:

- `json109-2.1` object-member path points at an array, not an array element
- `json109-2.2` missing object member is not itself an array element
- `json109-2.3` missing object member plus `[0]` creates an array
- `json109-2.4` missing deep object path plus `[0]` creates nested structure
- `json109-2.5` malformed array-index path rejects the call
- `json109-2.6` object path without an array index rejects the call
- `json109-2.7` root array index against an object is a no-op
- `json109-2.8` later invalid path rejects a multi-pair call atomically

Patch movement:

- Added `SQLiteRealUpstreamJson109ArrayInsertErrorMatrixDynamicTest.php`.
- Adds 2,251 focused TestRunner PASS cases and 5,379 behavior assertions.
- Covers text JSON, JSONB, and SELECT-expression dispatch for the upstream error matrix.
- Non-overlap: existing JSON109 bulk coverage focuses successful root-array insertion; existing atomic coverage focuses one late-error shape. This slice owns the full upstream `json109-2.1` through `2.8` invalid/mixed-path matrix.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109ArrayInsertErrorMatrixDynamicTest.php`
- Result: `1 test files, 5379 assertions, 0 failures`

Dependency closure:

- No new support component needed. This reuses the existing JSONB encoder/decoder, JSON path parser, array-insert editor, canonical JSON helpers, and SELECT expression dispatch.

Root harness:

- Not run - isolated micro-slice.
