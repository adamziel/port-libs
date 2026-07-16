## B-tree Vacuum Method Consolidation

Removed the late numbered `tableLeafFromDeleteResultNext264`,
`tableLeafFromDeleteResultNext265`, `tableLeafFromDeleteResultNext266`, and
`tableLeafFromDeleteResultNext295` through `tableLeafFromDeleteResultNext330`
production forwarding methods from
`SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`.

The stable canonical entrypoint remains
`tableLeafFreelistSpliceFromDeleteResult()`, which already routes the same
freelist-splice behavior through the descriptive helper path. No direct
test/example callers of the removed numbered methods were present.

Verification:

- `rg -n "tableLeafFromDeleteResultNext(264|265|266|29[5-9]|3[0-2][0-9]|330)" lanes/libsqlite/src lanes/libsqlite/tests lanes/libsqlite/examples`
  returned no matches.
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext331334Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext335342Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext343350Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext351358Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext359366Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext367374Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext375382Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext383390Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext391398Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext399414Test.php`
  passed: `11 test files, 3152 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed; this is a production
method-wrapper consolidation only.
