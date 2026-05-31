# real-upstream-corpus-window-functions-dynamic-20260531T072912Z-0

Implemented a real upstream window-function dynamic batch under
`lanes/libsqlite/tests/SQLiteRealUpstreamWindow9ExistsBetweenDynamic20260531Test.php`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test`
  section `6.1`: `EXISTS` over a subquery containing `MIN(...) OVER ()` and
  `CUME_DIST() OVER ()`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test`
  section `6.2`: the same `EXISTS` window subquery compared with
  `BETWEEN 1 AND 1`.

Focused movement:

- Added 1,004 distinct TestRunner PASS cases.
- Added 4,006 focused assertions.
- No production source changes.
- No lane-status counter edit; the integrator should count this as focused
  PASS-line growth after accepting the test batch on current base.

Non-overlap:

This owns the `window9.test` `6.1-6.2` EXISTS/BETWEEN subquery truth surface.
It avoids the accepted `window8`/`filter1` GROUPS+FILTER batch, prior
`window9` collation/filter and aggregate-order/subquery cases, `windowD`
truth tests, JSON/WAL/VFS/B-tree surfaces, grouped SELECT text, expression
ORDER BY, and metadata-only upstream-runner rows.

Dependency closure:

No new support component is needed. The batch reuses native
`SQLiteWindowFunction` min/cume_dist helpers and `SQLiteSelectPredicate`
EXISTS/BETWEEN evaluation against a lane-local independent oracle.
