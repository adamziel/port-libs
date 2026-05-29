# SQLite Suite Denominator Artifact Admission

This consolidation pass renames the remaining numbered production wrapper in
this family to the stable
`SQLiteUpstreamSuiteEvidence::suiteDenominatorArtifactAdmission()` entrypoint.
The lane-local admission behavior for accepted-HEAD focused runner artifacts is
unchanged.

It does not launch a broad SQLite `testfixture`, `release`, `all`, `make test`,
or `mptest` run. It composes supplied artifact rows with current accepted HEAD
provenance, duplicate broad-runner snapshots, and exact focused TestRunner
PASS-line output. A row only moves the mapped suite denominator when the
artifact is from the accepted repository head, includes a lane-local artifact
path, has human evidence text, names concrete `.test` scripts, and does not
regress a previously countable artifact.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteDenominatorArtifactAdmissionTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 520 assertions, 0 failures
```

The focused run emitted 80 PASS lines, moving this isolated lane status from
25285 to 25365 PHP PASS lines. `UPSTREAM_TEST_MANIFEST.json` maps one new
focused suite-denominator admission script, moving mapped coverage from 463 to
464 / 1589. Release/all parity remains explicitly uncounted.

Non-overlap: this avoids accepted denominator admission behavior handoffs,
release-blocker closure records, artifact-set admission, active-runner pgrep
filtering, foreground snapshot parsing, and SQL/JSON/WAL/B-tree/VFS
implementation clusters.

Dependency closure: no new support component is needed; this reuses lane-local
artifact rows, accepted-HEAD provenance strings, active-runner snapshot
parsing, and focused PHP TestRunner output only.
