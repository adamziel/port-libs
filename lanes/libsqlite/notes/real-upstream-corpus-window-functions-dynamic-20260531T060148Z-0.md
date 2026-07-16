# Real Upstream Window Functions Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-window-20260531T060148Z`

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

Added focused PHP corpus coverage in `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindow8GroupsDynamicTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
- Ported sections: `1.1.1-1.1.8` and `1.2.1-1.2.2`

Behavior covered:

- GROUPS frame aggregate evaluation over the real `window8.test` 80-row peer dataset.
- Single-term and multi-term peer keys matching `ORDER BY a` and `ORDER BY a,b`.
- `sum`, `count`, `min`, and `max` over `UNBOUNDED PRECEDING`, `CURRENT ROW`, numeric PRECEDING/FOLLOWING, and `UNBOUNDED FOLLOWING` boundaries.
- `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES` behavior.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindow8GroupsDynamicTest.php`
  - `No syntax errors detected`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindow8GroupsDynamicTest.php`
  - `1 test files, 4820 assertions, 0 failures`
  - `1211` focused PASS lines

Expected dashboard movement:

- `phpPass`: `2408856 -> 2410067` if accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`.

Non-overlap:

- Does not repeat accepted windowE sum/total overflow frames, window9 collation/FILTER behavior, window4 lead/lag/ntile/value helpers, value-function dynamic coverage, or prior window-pushdown/window-correlated-FILTER batches.
- This slice targets `window8.test` GROUPS peer aggregate frames with independent expected calculations.

Dependency closure:

- No new support component needed; reuses lane-local `SQLiteWindowFunction` GROUPS frame aggregate evaluation.

Root harness:

- Not run - isolated micro-slice.
