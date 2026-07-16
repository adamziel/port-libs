# real-upstream-corpus-json1-jsonb-dynamic-20260531T093302Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  - `json102-1000..1000b`: `json_each()` over phone arrays/scalars with prefix filtering
  - `json102-1110..1132`: `json_tree()` subtree search with explicit root paths
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`
  - `json106-ii.2..ii.7`: random JSON `json_tree()` atom/path invariants plus remove/insert/patch behavior

Lane patch:

- Adds `SQLiteRealUpstreamJson102TreeSearchDynamicCorpusTest.php`.
- Uses 250 deterministic generic application JSON documents.
- Adds 1001 focused TestRunner cases:
  - 250 text/JSONB `json_tree()` scalar fullkey/type/atom parity cases.
  - 250 path-rooted `json_tree()` UUID search cases.
  - 250 text/JSONB `json_each()` phone-prefix cases.
  - 250 `json_remove()` / `json_insert()` / `json_patch()` and JSONB parity cases.
  - 1 source-citation case tying the batch to the exact upstream files/ranges.

Non-overlap:

- This does not add metadata-only rows or generated upstream script ids.
- This does not repeat the accepted JSON table cursor/source/hidden/visible constraint clusters.
- This does not introduce domain-specific names; fixtures use generic asset/contact/partlist documents.

Dependency closure:

- No new support component is needed. The batch reuses existing native JSON1/JSONB helpers:
  `SQLiteJsonTree`, `SQLiteJsonEach`, `SQLiteJsonExtract`, `SQLiteJsonRemove`,
  `SQLiteJsonMutation`, `SQLiteJsonPatch`, `SQLiteJsonB`, and `SQLiteJsonCanonical`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102TreeSearchDynamicCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102TreeSearchDynamicCorpusTest.php`
  - `1 test files, 19587 assertions, 0 failures`
  - 1001 focused PASS cases
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed

Root harness:

- Not run - isolated micro-slice.
