# Release runner suite progress map current next48

- Slice: `yield-sqlite-release-runner-suite-progress-map-current-next48`.
- Behavior: adds `SQLiteUpstreamSuiteEvidence::releaseRunnerSuiteProgressMap()` to map current-vs-next suite progress rows by tier, countable status, test totals, advancement, preservation, regression, and open blockers.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteProgressMapTest.php`.
- Focused output: `1 test files, 379 assertions, 0 failures`, with 57 independent `PASS current next48 ...` lines.
- Status delta: `lane-status.json` `phpPass` moves from `17373` to `17430`, using the exact verified +57 PASS-line delta.
- Non-overlap: this avoids accepted batch23/batch35/batch36/batch37 release-runner surfaces for guarded preflight, artifact hydration, canonical mapping, suite ledger count movement, upstream gap proof, artifact-directory evidence, and all JSON/VFS/WAL/B-tree/SQL execution clusters. It narrows current-next48 to suite progress row classification and focused PHP admission only.
- Dependency closure: no new support component is needed; the slice composes lane-local suite row metadata and focused `TestRunner` output.
- Next task: use the progress map to publish only advanced next-source suite rows and keep release/all parity blocked until zero-error guarded runner artifacts exist with accepted-head provenance.
