# real-upstream-corpus-json1-jsonb-dynamic-20260531T070814Z-0

Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`.

Added `SQLiteRealUpstreamJson102InspectionDynamicMatrixTest.php`, a real upstream JSON1/JSONB inspection corpus slice based on hydrated upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`.

Upstream sections cited:

- `json102-190..240`: `json_array_length()` root, nested path, scalar, object, and missing-path behavior.
- `json102-500..560`: `json_type()` scalar/object/array/null/boolean classification behavior.
- `json105` reverse `#-N` path extension used by SQLite JSON path inspection.

Focused behavior:

- 500 dynamic documents with nested arrays, objects, booleans, null, real values, and missing paths.
- Text JSON, JSONB blob, JSON subtype, and `SQLiteSelectExpression` dispatch parity.
- 12 paths per document, covering root object zero length, nested array lengths, reverse array indexes, scalar length zero, missing-path NULL, and SQLite JSON type names.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102InspectionDynamicMatrixTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson102InspectionDynamicMatrixTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102InspectionDynamicMatrixTest.php`
  - `1 test files, 48005 assertions, 0 failures`
  - 2002 focused `PASS` lines.

Non-overlap:

- Does not repeat accepted JSON mutation, JSON table cursor/source/hidden/visible constraint, JSON aggregate/window, JSONB remove, JSON102 multi-path extraction, or JSON101 constructor batches.
- This slice focuses on inspection functions and SELECT-expression dispatch over dynamic JSON/JSONB inputs.

Dependency closure:

- No new support component needed; the existing `SQLiteJsonInspection`, `SQLiteJsonB`, `SQLiteJsonSubtypeValue`, and `SQLiteSelectExpression` implementations are reused.
