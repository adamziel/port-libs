# real-upstream-corpus-select-core-dynamic-20260530T183155Z-0

Status: ready for focused integration.

Added `SQLiteRealUpstreamSelect8LimitOffsetDynamicTest.php` as a real upstream
SELECT-core batch based on hydrated SQLite source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test`

Owned upstream behavior:

- Canonical `select8-1.1`, `select8-1.2`, and `select8-1.3` grouped aggregate
  LIMIT/OFFSET cases over the upstream `songs(songid, artist, timesplayed)`
  fixture.
- A dynamic 1,000-case matrix using the same upstream grouped query shape:
  `SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY
  LOWER(artist)` with varied `LIMIT` and `OFFSET` values, including negative
  LIMIT, zero LIMIT, in-range limits, over-large limits, and offsets beyond the
  grouped rowset.

Non-overlap:

- Prior SELECT-core slices covered `select1` through `select7` and selected
  `select9` compound LIMIT/OFFSET behavior. This slice owns `select8.test`
  grouped aggregate LIMIT/OFFSET behavior and does not claim mapped denominator
  growth or add fabricated upstream script ids.
- No domain-specific API, class, method, fixture name, or example was added.

Focused assertion/PASS movement:

- Adds 1,004 distinct focused TestRunner PASS cases in one real upstream
  behavior test file.
- Focused behavior assertions: 6,020 assertions in the new file.
- `phpPass` expected movement: `298721 -> 299725` if accepted as a focused
  PASS-line batch; mapped coverage remains `1189 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect8LimitOffsetDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect8LimitOffsetDynamicTest.php`
- Focused no-domain API guard: not run because this worktree does not contain
  `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`.
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing PHP TestRunner
  and `SQLiteSelectSql` executor for real upstream SELECT grouped aggregate
  LIMIT/OFFSET behavior.
