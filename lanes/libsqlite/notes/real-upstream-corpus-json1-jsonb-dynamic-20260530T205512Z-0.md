# real-upstream-corpus-json1-jsonb-dynamic-20260530T205512Z-0

Added `SQLiteRealUpstreamJson101EscapeValidityDynamicTest.php` as a real upstream JSON1/JSONB validity batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`

Ported scenario family:

- `json101-10.1` through `json101-10.95`
- `json101-10.86.0` through `json101-10.86.6`

The batch ports upstream string backslash escape validity behavior for all printable ASCII escape suffixes plus the partial and complete `\u` escape rows. Each real upstream row is checked across strict `json_valid()` entry points, flag coercion paths, text BLOB validation, whitespace-boundary wrappers, array/object embedding, valid-row JSONB canonical semantic parity, and invalid-row strict rejection.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101EscapeValidityDynamicTest.php`
- Result: `1 test files / 17912 assertions / 0 failures / 103 PASS lines`.

Non-overlap:

- This is `json101.test` string escape validity coverage and does not repeat prior `json106`/`json108` invariant batches, `json102` documentation rows, `json103` aggregate rows, `json105` path mutation, `json109` array insert, JSON table cursor/source/hidden/visible constraint work, or malformed JSONB planner surfaces.
- It uses real upstream scenario ids and does not add metadata-only admission records, fake upstream scripts, or domain-specific APIs.

Dashboard movement:

- Expected `phpPass` movement: `+103` focused PASS lines if the integrator admits this selected test.
- Mapped coverage: unchanged; this is behavior coverage over an already mapped upstream JSON script.

Dependency closure:

- No new support component is needed. The slice reuses existing native JSON validity, canonical JSON/JSONB, subtype, and lane-local PHP TestRunner behavior.
