2026-05-31 real-upstream-corpus-window-functions-dynamic

- Base accepted HEAD: 9f3a6190507c2ea8ee290883ee3ce143ab18c8c9.
- Upstream source truth: /home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test.
- Ported real upstream window3 dynamic scenarios over the upstream t2(a,b) corpus:
  window3.1.1.2.1, 1.1.2.2, 1.1.6.1, 1.1.9, 1.1.10, 1.1.11,
  1.1.12, 1.1.13, 1.1.14, 1.1.15, 1.2.2, 1.2.3, 1.2.4,
  1.2.5, 1.2.6, 1.3.1, 1.3.2, 1.3.3, 1.3.4, and 1.3.5.
- Focused behavior: dynamic ROWS/RANGE/GROUPS frame evaluation, UNBOUNDED/CURRENT/offset
  boundaries, EXCLUDE CURRENT ROW/GROUP/TIES, FILTER, max/min/count/sum/total/avg/
  group_concat aggregates, and first_value/last_value/nth_value value windows.
- Focused verification: php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamWindowDynamicCorpusTest.php
  reported 1 test file, 3820 assertions, 0 failures, with 20 PASS lines.
- Expected dashboard movement if accepted: +20 focused TestRunner PASS lines, +3820
  behavior assertions. Mapped denominator remains complete at 1589 / 1589.
- Non-overlap: avoids accepted window4 value behavior, window GROUPS/RANGE current-next18,
  JSON table windows, grouped SELECT SQL text, expression ORDER BY, and existing
  current-source numbered window helper families. This is a direct upstream window3
  dynamic corpus assertion batch, not metadata admission.
- Dependency closure: no new support component needed; this reuses the existing
  SQLiteWindowFunction frame/value/aggregate helpers and a lane-local independent
  oracle for the upstream t2 corpus.
