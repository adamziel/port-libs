# Real Upstream Corpus: Dynamic Window Functions

Slice: `real-upstream-corpus-window-functions-dynamic-20260530T163205Z-0`

Base accepted HEAD: `92b65fe2933444167e639234f5a0c525e1097aec`

Changed coverage:

- Added `SQLiteRealUpstreamWindowDynamicCorpusTest.php`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
    subtests `1.1`-`1.19`, `2.1`, `2.2.1`-`2.3.3`, `7.1`-`7.5`,
    `8.1`-`8.2`, `9.1`-`9.7`, and `10.1`-`10.3`.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
    subtests `10.1` and `10.2`.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
    dynamic `ROWS`, `RANGE`, `GROUPS`, ranking, `ntile`, and value-function
    frame families.

Focused assertion/PASS-line impact:

- New focused test file emits `1 test files, 635 assertions, 0 failures`.
- Dashboard expectation: `phpPass` `192937 -> 193572` from these 635 verified
  focused PASS lines.
- `benchmarkDenominator.mapped` remains unchanged at the accepted lane value;
  this is real upstream behavior coverage over already hydrated window corpus
  files, not a new manifest denominator row.

Non-overlap:

- Avoids accepted `window2` dynamic corpus, named-window RANGE/GROUPS root-gate
  hardening, JSON aggregate/window, compound recursive window helper families,
  and legacy application-shaped row-value window fixtures.
- Uses only generic application rows and native PHP window helpers.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  `SQLiteWindowFunction` ranking, offset, aggregate-frame, and value-frame
  primitives.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicCorpusTest.php`
  passed: `1 test files, 635 assertions, 0 failures`.
- API guard and `git diff --check -- lanes/libsqlite` are run in final worker
  verification.
