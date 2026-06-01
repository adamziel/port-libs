# real-upstream-corpus-btree-index-dynamic-skipscan3-20260601T064004Z

Status: focused real upstream corpus PASS-case growth on launcher base
`0beac79ced31a7dd838adc7168578a431ce35af2`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/skipscan3.test`
  - Ported `skipscan3-1.1` through `skipscan3-1.3`: rowid table
    `PRIMARY KEY(a,b,c)` setup plus `+a=1 AND c=?` and `a=1 AND c=?`
    intermediate-term skip-scan probes.
  - Ported `skipscan3-2.1` through `skipscan3-2.3`: the same probes on a
    `WITHOUT ROWID` primary-key b-tree.

Focused behavior:

- Adds `SQLiteBTreeIndexDynamicCorpusPlan::skipscan3IntermediateTermCases()`.
- Adds `SQLiteRealUpstreamBtreeSkipscan3IntermediateDynamicTest.php`.
- Adds 1003 distinct TestRunner PASS cases and 29513 behavior assertions.
- The cases vary rowid versus WITHOUT ROWID storage, unary-plus guarded versus
  ordinary `a=1` predicates, and 250 dynamic `c` probes that must return the
  matching `xNNNN` row while preserving `ANY(a) AND ANY(b)` skip-scan planning.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan3IntermediateDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan3IntermediateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan3IntermediateDynamicTest.php`
  - `1 test files, 29513 assertions, 0 failures`

Non-overlap:

- This does not repeat accepted `skipscan1.test` dynamic planner coverage,
  accepted `skipscan2.test` optoverview duplicate-threshold coverage, or the
  accepted `indexexpr2.test` update/open-index dynamic corpus.
- The new surface is upstream `skipscan3.test` intermediate-term skip-scan
  behavior where the planner probes `c=?` through `PRIMARY KEY(a,b,c)` while
  skipping `b` on both rowid and WITHOUT ROWID table shapes.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local
  B-tree/index dynamic corpus planner and existing TestRunner harness.
