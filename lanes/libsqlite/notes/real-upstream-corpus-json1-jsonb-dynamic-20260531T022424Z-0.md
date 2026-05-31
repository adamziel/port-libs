# real-upstream-corpus-json1-jsonb-dynamic-20260531T022424Z-0

- Base accepted HEAD: `5237a0589958b13a7df177706c832014179deb3d`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test` sections `json102-250..310` for multi-path `json_extract` / `jsonb_extract`, plus the reverse path `[#-N]` behavior covered by `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`.
- Added focused PHP corpus: `lanes/libsqlite/tests/SQLiteRealUpstreamJson102MultiPathDynamicCorpusTest.php`.
- Focused coverage: 250 generated mixed documents across four upstream path groups, with text input, JSONB input, `json_extract`, `jsonb_extract`, and `SQLiteSelectExpression` dispatch for each group.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102MultiPathDynamicCorpusTest.php` passed with `1 test files, 19005 assertions, 0 failures` and 1002 PASS cases.
- Non-overlap: this does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window work, JSON109 array-insert bulk work, or JSONB01 remove corpus work. It expands the real upstream JSON102 multi-path extraction matrix over mixed documents and JSON subtype dispatch.
- Dependency closure: no new support component is needed; the slice reuses existing native PHP JSONB, JSON extraction, JSON canonicalization, and SELECT expression dispatch components.
