# real-upstream-corpus-select-core-dynamic-20260530T181309Z-0

- Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`.
- Ported scenario family: upstream `test_compound_select` LIMIT/OFFSET expansion for `select9-1.2` through `select9-1.6`, covering `UNION ALL` compound SELECT row windows with table order, `ORDER BY 1`, `ORDER BY 2`, `ORDER BY 1, 2`, and `ORDER BY 2, 1`.
- Focused pre-edit run for `SQLiteRealUpstreamSelectCoreDynamicCorpusTest.php`: `1 test files, 12638 assertions, 0 failures`, with `1209` PASS lines.
- Focused post-edit run for `SQLiteRealUpstreamSelectCoreDynamicCorpusTest.php`: `1 test files, 58188 assertions, 0 failures`, with `3634` PASS lines.
- Countable delta: `+2425` real focused TestRunner PASS cases and `+45550` behavior assertions.
- Non-overlap: this extends the existing real upstream SELECT core dynamic corpus with `select9.test` compound LIMIT/OFFSET behavior. It does not add metadata-only denominator rows, fabricated script ids, WordPress-shaped APIs, or repeat accepted grouped SELECT text, expression ORDER BY, JSON table source/cursor/constraint work, WAL/VFS/B-tree clusters, or earlier select1-select8 dynamic coverage.
- Exclusion/blocker: unordered `UNION` and `INTERSECT` shapes from `select9-1.7` and `select9-1.11` exposed a current executor ordering mismatch against SQLite's expected upstream order, so this slice does not count those as passing coverage. A follow-up behavior slice should fix compound distinct/intersect output ordering before admitting those dynamic windows.
- Dependency closure: no new support component is needed; the existing native `SQLiteSelectSql` compound SELECT executor is reused.
