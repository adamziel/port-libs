# real-upstream-corpus-window-functions-dynamic-20260531T070147Z-0

- Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
    sections `2.1`, `2.2.1`-`2.2.3`, and `2.3.1`-`2.3.3`.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
    sections `10.1.1`-`10.1.5` and `10.2.1`-`10.2.6`.
- Added lane test:
  `lanes/libsqlite/tests/SQLiteRealUpstreamWindowValueOffsetDynamicTest.php`.
- Focused behavior:
  - `lead()` default offset, explicit offset, and explicit default.
  - `lag()` default offset, explicit offset, and explicit default.
  - `nth_value()` per-row second argument over the default `ORDER BY` frame
    (`UNBOUNDED PRECEDING` through `CURRENT ROW`).
  - `nth_value()` accepted integer-like coercions and rejected zero, negative,
    trailing-text, null, and non-integer indexes.
  - 1,000 deterministic dynamic partition/order/default/offset cases backed
    by independent PHP oracles.
- Focused verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowValueOffsetDynamicTest.php`
    -> no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowValueOffsetDynamicTest.php`
    -> `1 test files, 4019 assertions, 0 failures`; 1,005 PASS lines.
- Expected selected movement: `+1005` focused TestRunner PASS cases if accepted.
- Mapped denominator movement: none; upstream manifest is already complete at
  `1589 / 1589`.
- Non-overlap:
  - This avoids the accepted window3 ranking/distribution, window1 lead-limit,
    window7 groups/range, window9 collation/filter, window12 frame, and dynamic
    aggregate frame clusters.
  - This slice is specifically window4/window6 value-offset/index coercion
    behavior and does not add production APIs, WordPress-named surfaces, or
    metadata-only admission rows.
- Dependency closure: no new support component needed; the slice reuses
  `SQLiteWindowFunction` value-offset helpers and pure PHP focused tests.
- Root harness: not run - isolated micro-slice.
