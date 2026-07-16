# bulk-upstream-veryquick-shard-expansion-dynamic-20260530T175351Z-0

- Scope: real upstream SELECT executor corpus expansion for the bulk-throughput lane.
- Source truth: hydrated upstream SQLite files `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`, `select5.test`, and `select6.test`.
- Upstream scenarios: `select2-1.1` nested predicates, `select2-2.2` range/count predicates, `select2-3.1` commuted equality, `select2-3.2` direct equality, plus ordered LIMIT windows matching `select5`/`select6` row-order behavior.
- Changed PHP test: `SQLiteRealUpstreamSelectCoreDynamicCorpusTest.php`.
- Before count from accepted `HEAD`: `89` PASS cases / `1863` assertions / `0` failures for the focused file.
- After count: `1209` PASS cases / `12638` assertions / `0` failures for the focused file.
- Honest focused delta: `+1120` PASS cases and `+10775` assertions.
- Dashboard expectation: `phpPass` can move from `219557` to `220677` if the integrator accepts this focused PASS-line delta; mapped denominator remains `958 / 1589` because no new manifest row is claimed.
- Non-overlap: extends the existing real upstream SELECT core dynamic corpus with row-varying equality, commuted equality, range, descending-order, and LIMIT windows. It does not add fabricated `.test` script ids, upstream-runner metadata rows, WordPress-shaped APIs, or denominator inflation.
- Dependency closure: no new support component is needed; the slice reuses the native `SQLiteSelectSql` parser/executor and existing lane `TestRunner`.
