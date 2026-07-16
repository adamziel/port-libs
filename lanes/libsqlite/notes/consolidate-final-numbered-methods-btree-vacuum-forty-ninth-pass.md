## Consolidate Final Numbered Methods B-tree Vacuum Forty-ninth Pass

Slice: `consolidate-final-numbered-methods-btree-vacuum-forty-ninth-pass`

Changed the B-tree vacuum pointer-map/freeblock publication-seal handoff from
the numbered `next227` production receipt names to stable publication-seal
names. Direct finalization and final-handoff consumers now read
`current_source_publication_seal_token`, and the direct test/example files were
renamed to remove the numbered production suffix from this touched segment.

Verification:

- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-publication-seal.php && php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php && php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockPublicationSealTest.php && php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext230Test.php && php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFinalHandoffTest.php`
  - passed; no syntax errors detected
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockPublicationSealTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext230Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFinalHandoffTest.php`
  - passed; `3 test files, 3869 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-publication-seal.php --self-test`
  - passed; emitted `application-btree-vacuum-pointermap-freeblock-current-source-publication-seal self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed
- exact user-named suffix scan over `lanes/libsqlite/src`,
  `lanes/libsqlite/tests`, and `lanes/libsqlite/examples`
  - no matches

Dependency closure: no new support component needed; this is a naming
consolidation over existing B-tree vacuum pointer-map/freeblock publication
seal behavior.
