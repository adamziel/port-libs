# real-upstream-corpus-json1-jsonb-dynamic-20260530T174614Z-0

Status: ready for integration from isolated worktree

Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`

Added focused PHP coverage in
`lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicFollowupTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  - `json102-100`, `100b`, `120-3`, `140b`, `150b`, `160-4`, `170b`, `180-4`
  - `json102-250`, `260`, `270`, `280`, `290`, `300`, `310`, `320`, `330`, `340`, `350`, `360`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
  - `json105-1.70`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
  - `json107-1.1`, `1.1.1`, `1.1.2`, `1.1.4`, `1.1.8`, `1.2.3`, `1.3`, `1.4`, `1.5`, `1.6`, `1.7`, `1.8`, `2.1`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`
  - `json108-1.1`, `1.3`, `1.4`, `1.5` invariant shape, using deterministic JSON/JSON5 documents instead of the upstream `randomjson` extension.

Focused coverage delta:

- 57 new TestRunner PASS cases
- 456 focused assertions
- Non-overlap: complements the existing `SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  and `SQLiteRealUpstreamJsonDynamicPathCorpusTest.php` by adding JSON BLOB-as-text
  compatibility, JSONB/text parity for extract/mutation/constructor behavior, and
  deterministic `json_pretty()` canonical round-trip invariants. It does not add
  metadata-only admission rows or generated fake upstream IDs.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicFollowupTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicFollowupTest.php`
  - `1 test files, 456 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicPathCorpusTest.php`
  - `3 test files, 1462 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP JSON,
  JSONB, JSON5, `json_pretty()`, JSON table, and TestRunner components.

Mapped denominator:

- No `UPSTREAM_TEST_MANIFEST.json` denominator growth is claimed. These are real
  upstream-sourced PHP behavior assertions over already known JSON corpus files.
