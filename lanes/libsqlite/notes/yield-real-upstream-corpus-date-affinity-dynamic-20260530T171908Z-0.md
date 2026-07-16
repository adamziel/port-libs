# real-upstream-corpus-date-affinity-dynamic-20260530T171908Z-0

Added `SQLiteRealUpstreamDate2DeterministicDynamicCorpusTest.php` as an additive real upstream corpus slice.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `date2-331`: generated `datetime(b) BETWEEN '2017-07-04' AND '2017-07-08'` range behavior over `julianday('2017-07-01') + rowid`.
- `date2-500`: deterministic date/time modifier matrix accepted in expression-index predicates.

Focused coverage:
- 620 generated row tests for deterministic julianday-to-datetime conversion and range predicate truth values.
- 68 modifier tests covering the 17 deterministic `date2-500` modifiers across four generated rows.
- 1 generic application retention-window test for numeric/text Julian day affinity.
- Total new focused result: 689 PASS lines / 1309 assertions.

Non-overlap:
- Existing `SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` already covers `date.test`, `date4.test`, and `date5.test`.
- This slice ports `date2.test` deterministic expression-index/check-style behavior and does not repeat the existing date affinity dynamic file.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicDynamicCorpusTest.php` -> `1 test files, 1309 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNodomainSpecificApiTest.php` -> not run; guard file is not present in this worktree.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicDynamicCorpusTest.php` -> no syntax errors.
- `git diff --check -- lanes/libsqlite` -> passed.

Dependency closure:
- No new support component is needed. The slice reuses existing `SQLiteCoreScalarFunction` date/time behavior.
