# real-upstream-corpus-json1-jsonb-dynamic-20260531T050321Z-0

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`.

Ported scenario cluster:

- `json103-100`: empty `json_group_array()` emits `[]`.
- `json103-101`: aggregate values reject non-JSON BLOBs.
- `json103-200`: empty `json_group_object()` emits `{}`.
- `json103-201`: object aggregate values reject non-JSON BLOBs.
- `json103-300`: JSON subtype values inside `json_group_array()` remain structural while adjacent plain text remains quoted.

Added `SQLiteRealUpstreamJson103SubtypeResetDynamicTest.php` with 721 focused PASS cases and 5767 assertions. The test uses generic `app_settings` rows and direct aggregate calls to cover text JSON and JSONB aggregate output parity across 360 dynamic cases. It does not add new public APIs, source components, or WordPress-specific names.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103SubtypeResetDynamicTest.php`
- Result: `1 test files, 5767 assertions, 0 failures`

Dependency closure: no new support component is needed; this reuses existing `SQLiteJsonAggregate`, `SQLiteJsonSubtypeValue`, `SQLiteJsonB`, and `SQLiteSelectSql` behavior.

Non-overlap: this does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSON107 legacy BLOB matrices, JSON108 pretty invariants, or JSON109 array-insert matrices. It targets JSON103 subtype-reset aggregate behavior and BLOB aggregate rejection.
