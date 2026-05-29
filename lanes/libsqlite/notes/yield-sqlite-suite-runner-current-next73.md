# SQLite Suite Runner Current/Next73

Slice: `suite-denominator-runner-current-next73`

This slice adds a lane-local current/next admission gate for focused SQLite
runner audit/log artifact pairs. It is intentionally narrower than accepted
current-next65 denominator rows, current-next68 accepted-head artifact
admission, and current-next69 current-source freshness checks.

The new gate admits runner denominator movement only when:

- the runner row is tied to the current accepted source head;
- the audit and log artifact paths are lane-local under `lanes/libsqlite`;
- the guarded command names `testfixture` and `testrunner.tcl`;
- audit and log parsed test counts match and both report zero errors;
- the focused PHP TestRunner output has exactly one selected file and the
  expected PASS-line delta;
- no active broad all/release/mptest runner is present; and
- release/all parity is not claimed.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteDenominatorRunnerCurrentNext73Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 606 assertions, 0 failures
```

Expected dashboard movement: `phpPass +91` from this focused test file when
accepted. Mapped upstream coverage remains unchanged because the row validates
runner artifact-pair countability only; it does not add a new upstream inventory
unit or claim broad release/all parity.

Dependency closure: no new support component is needed. The slice composes
lane-local row metadata, accepted-source heads, guarded runner command strings,
duplicate-runner gates, and focused TestRunner PASS-line output only.
