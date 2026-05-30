# real-upstream-corpus-json1-jsonb-dynamic-20260530T233346Z-0

Added `SQLiteRealUpstreamJson1JsonbDynamicPathMatrixTest.php` as a non-overlapping real upstream JSON1/JSONB behavior matrix.

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

Scenario coverage:
- json101 constructor/canonical JSON round trip behavior.
- json102 `json_type`, `json_array_length`, `json_extract`, and `jsonb_extract` path behavior.
- json105 reverse array index and malformed reverse-path behavior.
- jsonb01 text/JSONB `json_remove` parity for object, array, missing, append, and reverse-index paths.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicPathMatrixTest.php`
- Result: `1 test files, 6723 assertions, 0 failures`
- PASS-line delta: `+1281` focused TestRunner cases.

Dependency closure:
- No new support component is needed. The slice reuses existing native JSON support (`SQLiteJsonB`, `SQLiteJsonExtract`, `SQLiteJsonInspection`, `SQLiteJsonMutation`, `SQLiteJsonRemove`, `SQLiteJsonCanonical`, and `SQLiteJsonSubtypeValue`).

Non-overlap:
- Does not touch production source, dashboard/root publication files, WordPress-specific APIs, or mapped denominator rows.
- Avoids accepted JSON table cursor/source/hidden/visible constraint clusters, json107 legacy BLOB text coverage, json108 pretty invariants, json109 array insert coverage, and prior json105/jsonb01 static fixtures by using distinct generated application documents and a combined path/mutation/remove matrix.
