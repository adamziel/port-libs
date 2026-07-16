# real-upstream-corpus-json1-jsonb-dynamic-20260531T064003Z-0

Base accepted HEAD: `adb26e7f16ecd89937cf2d16ad3f15841131934b`.

Added `SQLiteRealUpstreamJson102MultiPathDynamic20260531Test.php`, a real
upstream-backed JSON1/JSONB dynamic extraction corpus from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`.

Owned upstream sections:

- `json102-250` root object extraction.
- `json102-260` nested array extraction.
- `json102-270` nested object extraction.
- `json102-280` nested scalar extraction.
- `json102-290` multi-path extraction.
- `json102-300` missing path SQL NULL.
- `json102-310` missing plus scalar multi-path extraction.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102MultiPathDynamic20260531Test.php`
  - `No syntax errors detected`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102MultiPathDynamic20260531Test.php`
  - `1 test files, 22003 assertions, 0 failures`
  - `1001` TestRunner PASS lines.

Non-overlap:

- Avoids accepted JSON table cursor/source/hidden/visible constraint clusters,
  JSON103 aggregate/window batches, JSON105 reverse-index/current-index
  mutation batches, JSON501/502 escaped-label/control-character batches,
  JSONB removal/malformed batches, and generic suite metadata-only admission.
- This slice focuses on json102 extraction result typing and multi-path JSON
  array construction over dynamic text JSON and JSONB documents, including
  SELECT-expression dispatch.

Dependency closure:

- No new support component needed; existing `SQLiteJsonExtract`,
  `SQLiteJsonB`, `SQLiteJsonCanonical`, `SQLiteJsonInspection`, and
  `SQLiteSelectExpression` behavior is reused.
