# WAL readmark salt checksum recovery current-source next115

Status: focused PHP behavior growth for a WAL recovery edge where SHM read marks must be evaluated against the recovered WAL generation, not blindly carried across a restarted WAL salt.

This slice adds `SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan`. It composes existing WAL transaction checksum recovery with SHM read-mark recovery:

- recover the current WAL to its committed prefix before preserving current read marks;
- checkpoint that recovered current source before opening the next WAL generation;
- recover the restarted next WAL while discarding stale old-salt tail frames;
- preserve next-generation SHM read marks only when the SHM salt matches the recovered restarted WAL;
- rebuild stale old-salt SHM read marks so the next reader uses the recovered latest committed source instead of an invalid old-salt frame pin.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNext115Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 72 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-readmark-salt-checksum-recovery-current-source-next115.php --self-test
application-wal-readmark-salt-checksum-recovery-current-source-next115 self-test passed
```

Expected dashboard movement: `phpPass` +72, from `43574` to `43646`, from the 72 independent PASS lines in the focused test. `benchmarkDenominator.mapped` is unchanged because this is current-source PHP behavior over already mapped WAL checksum/read-mark recovery inventory rather than a freshly hydrated upstream Tcl unit.

Non-overlap: avoids accepted WAL checksum/salt recovery next106, WAL SHM read-mark recovery next70, WAL reader checkpoint snapshots batch107/108, WAL savepoint byte truncation, VFS savepoint rollback apply, rollback-journal apply/commit, WAL checkpoint transaction planning, VFS SHM lock-byte/file-control next112, and accepted pager cache-spill/hot-journal clusters. The new behavior is the composition point where read marks are preserved or rebuilt after checksum recovery chooses the current and next WAL generations.

Dependency closure: no new support component is needed. The slice reuses the native PHP WAL checksum/transaction recovery boundary, SHM read-mark recovery, reader snapshot, and checkpoint database-image primitives.

Next task: continue with broader WAL/pager durability application or a distinct checkpoint/reset/current-source edge; avoid another salt/read-mark wrapper unless it applies a new pager state transition.
