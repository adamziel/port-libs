### 2026-05-27 upstream-suite admission current-next56

Scope: added `SQLiteUpstreamSuiteEvidence::focusedPhpPassCurrentHeadAdmission()`
as a current-source admission gate for focused PHP TestRunner evidence.

The new record removes a countability blocker for `lane-status.json` `phpPass`:
focused PHP movement is admitted only when all of these hold:

- evidence repository HEAD equals the accepted repository HEAD;
- TestRunner output is a focused run for exactly one lane-local test file;
- failures are zero;
- phpPass delta is the exact number of `PASS` lines, not raw assertions;
- any supplied expected delta matches that exact PASS-line count.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteAdmissionCurrentNext56Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 265 assertions, 0 failures
```

Focused PASS-line delta: `+56`, moving `phpPass` from `20008` to `20064`.
Mapped upstream denominator stays `462 / 1589`; this is an admission/countability
gate, not a newly mapped upstream inventory unit and not release/all parity.

Non-overlap: this does not repeat focused Tcl artifact admission, accepted-head
artifact-directory provenance, release-blocker closure, denominator audit,
release/all ledger reshaping, JSON table, VFS/WAL, B-tree, UTF/collation, or
SQL executor behavior. It narrows the current-source PHP PASS gate that the
integrator uses before accepting lane-local test growth.

Dependency closure: no new support component is needed; the gate reuses local
TestRunner output and accepted repository HEAD strings only.
