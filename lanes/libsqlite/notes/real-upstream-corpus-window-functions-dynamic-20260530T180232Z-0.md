# Real Upstream Corpus Window Functions Dynamic 20260530T180232Z-0

Status: added a focused real-upstream window aggregate frame batch for `window4.test` partitioned `RANGE` windows over `ttt(a,b,c)`.

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`: sections `4.5.1.1`, `4.5.1.2`, and `4.5.18.1` through `4.5.24.2`.

Implemented behavior:
- `SQLiteWindowDynamicUpstreamCorpusTest.php` now ports 16 additional upstream result rows for paired `max`, `min`, and `sum` window aggregates.
- The tests exercise partitioned `RANGE` frames over `UNBOUNDED PRECEDING`, `CURRENT ROW`, and `UNBOUNDED FOLLOWING` boundaries while preserving upstream row order.

Focused assertion movement:
- Before this slice shape: existing file had 44 PASS cases and 603 assertions.
- After this slice: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicUpstreamCorpusTest.php` passes `1 test files, 891 assertions, 0 failures`.
- Delta: +16 focused TestRunner PASS cases and +288 behavior assertions.

Expected movement: focused PASS-line growth only. This claims no new mapped denominator row and uses generic SQLite/application data, not domain-specific API names.

Dependency closure: no new support component is needed; this reuses existing native PHP window frame boundary and aggregate evaluation helpers.

Non-overlap: this avoids accepted `window1`/`window2` dynamic frame batches, prior `window4` ranking/lead/lag/nth-value coverage, `windowE` numeric RANGE cases, row-value RETURNING window handoffs, compound recursive window admission helpers, JSON aggregate windows, and suite-evidence metadata. The new surface is upstream `window4.test` paired aggregate outputs for partitioned `RANGE` frame boundary combinations.
