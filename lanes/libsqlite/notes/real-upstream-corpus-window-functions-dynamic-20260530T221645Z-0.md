# real-upstream-corpus-window-functions-dynamic-20260530T221645Z-0

- Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test`.
- Ported behavior cluster: `windowfault.test` sections `1` through `8` and `13`.
- Focused PHP coverage: added `SQLiteRealUpstreamWindowFaultDynamicTest.php`.
- Verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFaultDynamicTest.php` passed with `1 test files, 8011 assertions, 0 failures`.
- PASS-line movement: `+1011` selected focused TestRunner cases.
- Non-overlap: this owns `windowfault.test`, which had no local `windowfault` test before this slice. It does not repeat accepted `window1`, `window2`, `window3`, `window4`, `window5`, `window6`, `window7`, `window8`, `window9`, `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, or `windowerr` batches.
- Dependency closure: no new support component is needed. This reuses existing lane-local `SQLiteWindowFunction` ranking, value-frame, aggregate-frame, and dynamic frame helpers.
