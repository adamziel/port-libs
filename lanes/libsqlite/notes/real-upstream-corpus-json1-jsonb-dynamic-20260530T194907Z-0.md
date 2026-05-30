# real-upstream-corpus-json1-jsonb-dynamic-20260530T194907Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported scenario ranges: `json101-1.1` through `json101-1.4`, `json101-2.1` through `json101-2.5`, `json101-3.1` through `json101-3.5`, and `json101-4.1` through `json101-4.10`.

Patch summary:

- Added `SQLiteRealUpstreamJsonDynamicCorpusTest.php`.
- Covers JSON constructors, JSON subtype-vs-text value propagation, JSONB constructor roundtrips, `json_extract`, `jsonb_extract`, `json_type`, `json_array_length`, `json_set`, `jsonb_set`, `json_insert`, `json_replace`, `json_remove`, and no-op mutation preservation.
- Uses generic application documents only; no new domain-specific API or scenario names.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window batches, JSON subtype handoff, and malformed JSONB planner diagnostics. This batch is scalar JSON1/JSONB function behavior from `json101.test`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicCorpusTest.php`
- Result: `1 test files, 1440 assertions, 0 failures`
- Selected TestRunner PASS lines: `1312`.

Dependency closure:

- No new support component is needed. Existing `SQLiteJsonCanonical`, `SQLiteJsonConstructor`, `SQLiteJsonExtract`, `SQLiteJsonInspection`, `SQLiteJsonMutation`, `SQLiteJsonRemove`, `SQLiteJsonB`, `SQLiteBlobValue`, and `SQLiteJsonSubtypeValue` behavior is reused.

Next task:

- Continue with non-overlapping upstream JSON corpus sections in `json102.test`, `json103.test`, `json104.test`, `json106.test`, `json107.test`, `json108.test`, `json109.test`, or `jsonb01.test`, selecting behavior that exercises parser-level SQL execution or missing JSONB parity rather than metadata-only rows.
