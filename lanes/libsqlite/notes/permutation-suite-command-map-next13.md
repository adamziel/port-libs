# Permutation Suite Command Map - next13

Implemented a suite-runner blocker removal for the SQLite upstream release/all
evidence lane. `SQLiteUpstreamSuiteEvidence::permutationSuiteCommandMap()` now
turns a hydrated `test/permutations.test` inventory into concrete guarded
`testfixture` command records when all declared permutation suites are mapped and
the runner inputs are present. `releaseTierMatrix()`, `fullSuiteReadinessRecord()`,
and `fullSuiteCommandManifest()` now promote that map into runnable
permutation-suite release tier evidence instead of leaving the tier blocked on a
generic "concrete permutation suite command map" placeholder.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 859 assertions, 0 failures`
  - New PASS-line delta: `+3` focused PASS cases.

Status delta:

- `lane-status.json` `phpPass`: `3796 -> 3799`.
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory units were
  mapped, only a release-runner command-map blocker was removed.

Dependency closure:

- No new support component needed. The slice reuses the lane-local manifest,
  hydrated SQLite `permutations.test`, and the existing guarded `testfixture`
  command shape.

Non-overlap:

- This does not repeat accepted VFS writer/sync/lock/rollback, WAL byte
  truncation/checkpoint, JSON table cursor/source/constraint pushdown, B-tree
  page/root/overflow work, Unicode GLOB, SELECT subqueries/grouping/expression
  ORDER BY, or release-blocker closure/admission ledgers. It targets the
  remaining permutation-suite command-map blocker in the suite/readiness path.
