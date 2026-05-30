# Real Upstream SELECT Core Dynamic Corpus

This handoff ports focused behavior assertions from hydrated upstream SQLite
`test/select1.test`, `test/select2.test`, and `test/select3.test` into PHP
coverage for `SQLiteSelectSql`.

- Upstream sources: `select1.test` scenarios `select1-1.1`, `select1-1.4`
  through `select1-1.13`, selected `select1-2.2` through `select1-2.17`;
  `select2.test` scenarios `select2-1.1`, `select2-1.2`, `select2-2.1`,
  `select2-2.2`, `select2-3.1`, and `select2-3.2b/c`; `select3.test`
  scenarios `select3-1.0`, `select3-1.1`, and selected `select3-2.1` through
  `select3-2.12`.
- Focus: core SELECT projection, qualified joins, scalar `min()`/`max()`,
  aggregate `count()`/`min()`/`max()`/`sum()`, DISTINCT, predicates,
  ORDER BY, GROUP BY, aggregate aliases, and a missing-table error boundary.
- Non-overlap: this does not add suite metadata rows, fake `.test` names,
  domain-specific APIs, grouped SELECT status churn, JSON table wrappers,
  WAL/VFS/B-tree storage helpers, or another admission/provenance record.
- Dependency closure: no new support component is needed; it reuses the
  existing PHP `SQLiteSelectSql` executor and the hydrated upstream SQLite Tcl
  files as source truth.
