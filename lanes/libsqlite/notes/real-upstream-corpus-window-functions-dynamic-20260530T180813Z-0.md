# real-upstream-corpus-window-functions-dynamic-20260530T180813Z-0

- Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`.
- Ported upstream scenarios:
  - `window6.test:8.2` and `window6.test:8.3` for `ROWS 2 PRECEDING` shorthand and explicit `BETWEEN 2 PRECEDING AND CURRENT ROW` aggregate frames.
  - `window6.test:10.1.1-10.1.5` for `nth_value()` invalid second-argument rejection.
  - `window6.test:10.2.1-10.2.6` for `nth_value()` default ordered frame behavior and integral numeric argument coercion.
  - `window6.test:11.3.1-11.3.3` for equivalent current-row frame spellings over duplicate ORDER BY peers.
- Implementation delta: `SQLiteSelectQuery` now applies SQLite's default ordered frame to `first_value()`, `last_value()`, and `nth_value()` when no explicit frame is present, and window integer arguments accept integral real/text values while rejecting non-integral, NULL, zero, and negative values.
- Focused verification:
  - Before fix, `SQLiteRealUpstreamWindowValueDynamicTest.php` failed the `nth_value()` default-frame and integral text/real argument cases.
  - After fix, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowValueDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicFramesTest.php` passed: `2 test files, 43 assertions, 0 failures`.
- Non-overlap: this extends the accepted `window1.test`/`window2.test` dynamic frame corpus and the later `window3/window4` batch with `window6.test` value-function/default-frame behavior; it does not add metadata-only rows, generated fake upstream IDs, domain-shaped APIs, or duplicate JSON/WAL/B-tree/VFS surfaces.
- Dependency closure: no new support component is needed; the slice reuses native `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteWindowFunction` execution.
