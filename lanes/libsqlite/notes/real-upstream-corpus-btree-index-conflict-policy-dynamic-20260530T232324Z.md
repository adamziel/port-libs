# real-upstream-corpus-btree-index-conflict-policy-dynamic-20260530T232324Z

- Base accepted HEAD: `97bde16e3221376c9c3d6c7f9b2330b164322c56`.
- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T232324Z-0`.
- Source truth: hydrated SQLite upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`, sections `index-19.1` through `index-19.7`.
- Non-overlap: existing accepted late index lifecycle coverage starts at `index-20.1`; this owns the immediately preceding shared-index `ON CONFLICT` behavior and does not repeat accepted `index-20.1` through `index-23.1`, `index3`, `index4`, `index6`, `index7`, `index8`, `index9`, `indexA`, `indexedby`, or `autoindex` dynamic batches.
- Behavior added: dynamic corpus rows for shared UNIQUE/PRIMARY KEY autoindex conflict policy behavior, covering ABORT duplicate transaction preservation, ROLLBACK duplicate transaction rollback, conflicting `ON CONFLICT` clause rejection, and REINDEX preservation.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexConflictPolicyDynamicTest.php` passed with `1 test files, 20808 assertions, 0 failures`.
- Countable growth: `1002` focused TestRunner PASS cases from real upstream behavior.
- Dependency closure: no new support component needed; reuses lane-local index lifecycle, autoindex catalog, transaction-state, and conflict-policy helpers.
- Root harness: not run - isolated micro-slice.
