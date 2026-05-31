# real-upstream-corpus-window-functions-dynamic-20260531T020715Z-0

Added `SQLiteRealUpstreamWindow12FrameDynamicCorpusTest.php` as an additive
real upstream corpus slice over the hydrated SQLite upstream checkout.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  sections `4.4`, `4.9`, and `4.10.2`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
  sections `2.1` through `2.30`.

Focused coverage:

- 3 canonical `window1.test` checks for running `sum()`, running `avg()`,
  descending `count()`, and `group_concat()` window behavior.
- 5 canonical `window2.test` partitioned frame checks for unbounded, preceding,
  following, parity partition, and empty-frame `sum()` results.
- 1000 dynamic ROWS-frame cases expanding the same upstream frame semantics
  across `sum()`, `count()`, `first_value()`, `last_value()`, and SQL FILTER
  truth handling.
- Focused verification passed with `1 test files, 5012 assertions, 0 failures`
  and 1010 TestRunner PASS lines.

Non-overlap:

- This does not repeat existing `SQLiteRealUpstreamWindow9CollationFilterDynamicTest.php`
  coverage, `windowB` inverse RANGE remainder coverage, row-value RETURNING
  window current-source tests, JSON aggregate/window tests, parser-level SELECT
  text work, or metadata-only upstream admission rows.
- Mapped denominator coverage remains unchanged because the mapped inventory is
  already complete; this handoff should be counted as focused PASS-line and
  behavior-assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses the native
  `SQLiteWindowFunction` aggregate/value frame helpers over real upstream
  `window1.test` and `window2.test` behavior.
