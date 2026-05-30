# Real Upstream SELECT Core Dynamic Corpus

This handoff ports focused behavior assertions from hydrated upstream SQLite
`test/select1.test`, `test/select2.test`, `test/select3.test`, and
`test/select4.test` into PHP
coverage for `SQLiteSelectSql`.

- Upstream sources: `select1.test` scenarios `select1-1.1`, `select1-1.4`
  through `select1-1.13`, selected `select1-2.2` through `select1-2.17`;
  `select2.test` scenarios `select2-1.1`, `select2-1.2`, `select2-2.1`,
  `select2-2.2`, `select2-3.1`, and `select2-3.2b/c`; `select3.test`
  scenarios `select3-1.0`, `select3-1.1`, and selected `select3-2.1` through
  `select3-2.12`; `select4.test` scenarios `select4-1.0`, selected
  `select4-1.1`, `select4-2.1`, selected `select4-3.1`, selected
  `select4-4.1`, `select4-5.2`, `select4-5.4`, and `select4-6.1` through
  `select4-6.7`.
- Focus: core SELECT projection, qualified joins, scalar `min()`/`max()`,
  aggregate `count()`/`min()`/`max()`/`sum()`, DISTINCT, predicates,
  ORDER BY, GROUP BY, aggregate aliases, compound `UNION ALL`/`UNION`/
  `EXCEPT`/`INTERSECT`, compound ORDER BY aliases and ordinals, NULL
  distinctness in compound operators, and a missing-table error boundary.
- 2026-05-30 slice: added the `select4.test` compound SELECT batch and fixed
  two executor parser gaps it exposed: double-quoted projection aliases and
  compound ORDER BY matching against aggregate expression text after GROUP BY
  rewrite. Focused verification moved
  `SQLiteRealUpstreamSelectCoreDynamicCorpusTest.php` from `1117` assertions
  to `1863` assertions, a `+746` assertion delta and `+27` selected PASS
  lines.
- Non-overlap: this does not add suite metadata rows, fake `.test` names,
  domain-specific APIs, grouped SELECT status churn, JSON table wrappers,
  WAL/VFS/B-tree storage helpers, or another admission/provenance record.
- Dependency closure: no new support component is needed; it reuses the
  existing PHP `SQLiteSelectSql` executor and the hydrated upstream SQLite Tcl
  files as source truth.
