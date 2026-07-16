# real-upstream-corpus-json1-jsonb-dynamic-20260530T185840Z-0

Added `SQLiteRealUpstreamJson106InvariantBulkDynamicTest.php` as a real upstream JSON1/JSONB invariant batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`

Ported scenario family:

- `json106` loop invariants over JSON/JSON5 documents: strict and JSON5 validity, `json_tree()` scalar atom parity with path extraction, `json_remove()` plus `json_insert()` restoration for object scalar paths, `json_patch()` scalar preservation, JSONB patch/canonical parity, and `json_pretty()` round-trip canonicalization.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson106InvariantBulkDynamicTest.php`
- Result: `1 test files / 21760 assertions / 0 failures / 4 PASS lines`.

Non-overlap:

- This widens real upstream `json106.test` invariant behavior with 160 deterministic documents and does not add metadata-only rows, generated fake upstream script ids, or WordPress-shaped APIs.
- It does not repeat accepted JSON table cursor/source/hidden/visible constraint work, `json105` dynamic path mutation coverage, `json109` array-insert coverage, `json102` documentation scalar/path rows, `json103` aggregate coverage, `jsonb01` remove coverage, or the smaller existing `json106/json108` invariant file. The new focused assertion growth is from additional deterministic JSON/JSON5 corpus rows exercising the upstream invariant checks at larger volume.

Dashboard movement:

- Expected `phpPass` movement: +4 focused PASS lines if the integrator admits this file as a new selected test.
- Mapped coverage: unchanged; this is behavior coverage over an already mapped upstream JSON script, not a new denominator row.

Dependency closure:

- No new support component is needed. The slice reuses existing native JSON1/JSONB helpers and the lane-local PHP TestRunner.
