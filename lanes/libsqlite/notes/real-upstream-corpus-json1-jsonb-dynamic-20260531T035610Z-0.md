# real-upstream-corpus-json1-jsonb-dynamic-20260531T035610Z-0

Status: focused real-upstream JSONB corpus test growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
- Ported scenario range: `jsonb01-1.2.1..18` JSONB remove behavior plus `jsonb01-2.0` malformed JSONB rejection.

Focused PHP coverage:

- Added `SQLiteRealUpstreamJsonb01RemoveDynamicTest.php`.
- Adds 5,202 distinct focused TestRunner PASS cases and 7,805 assertions.
- Expands the real `jsonb01.test` object-member, nested-member, array-index, reverse-index, append-slot no-op, and missing-path no-op remove matrix across 100 distinct JSONB documents.
- Exercises `jsonb_remove()` and `json_remove()` with both JSONB and text JSON inputs.
- Includes the upstream malformed JSONB catchsql case using `x'8ce6ffffffff171333'`.

Non-overlap:

- This slice targets `jsonb01.test` JSONB remove behavior.
- It does not repeat accepted JSON105 reverse-index mutation/extract batches, JSON102 inspection/mutation, JSON101 escape handling, JSON104 patch behavior, JSON107 BLOB-text behavior, JSON109 array insert behavior, JSON501/JSON502 JSON5/path behavior, JSON table cursor/source/constraint work, or metadata-only suite rows.
- Mapped upstream denominator coverage remains complete at `1589 / 1589`; this is focused PASS-line/assertion growth only.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01RemoveDynamicTest.php`
  - `1 test files, 7805 assertions, 0 failures`
  - 5,202 PASS lines.

Dependency closure:

- No new support component is needed. This reuses the lane-local JSONB encoder/decoder and JSON remove implementation.
