# real-upstream-corpus-window-functions-dynamic-20260531T044411Z-0

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`.

This slice adds a real upstream SQLite window-function corpus batch derived
from `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`.
It ports the `window1.test` section 10 regional sales scenarios into a
dynamic PHP corpus against the lane-local `SQLiteWindowFunction` implementation.

Upstream source sections:

- `window1.test` 10.0: `sales(emp, region, total)` seed rows.
- `window1.test` 10.1: top two rows per region via partitioned `row_number()`.
- `window1.test` 10.2 through 10.4: partitioned cumulative `sum(total)` with
  stable region/total output ordering and LIMIT/OFFSET trimming.
- `window1.test` 10.5 through 10.6: `ROWS BETWEEN CURRENT ROW AND UNBOUNDED
  FOLLOWING` partition frames.

Ported PHP coverage:

- New focused test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1RegionalSalesDynamicTest.php`.
- Dynamic corpus size: 128 generated application rows, expanded from the real
  upstream `sales` seed rows across 16 cycles.
- Focused assertions: 1,281 passing assertions.
- Distinct behavior checks: partitioned top-N membership, prefix window sums,
  suffix/following-frame sums, `first_value`, `last_value`, `lead`, `lag`,
  rank monotonicity, `percent_rank` bounds, and `cume_dist` bounds across every
  generated row.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1RegionalSalesDynamicTest.php`
  -> `1 test files, 1281 assertions, 0 failures`.

Dependency closure:

- No new support component needed. This reuses the existing lane-local window
  function implementation and the existing focused PHP test harness.

Non-overlap:

- This does not add metadata-only admission rows, fake upstream test names, or
  domain-specific API surface. It avoids the already accepted window
  pushdown, window4/window9/windowE, groups/range, JSON-object inverse, and
  parser-level JSON table source coverage by focusing on `window1.test` section
  10 regional-sales partition and following-frame semantics.
