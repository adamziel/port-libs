# B-tree Vacuum Pointer-map Freeblock Current-source Consolidation 176-186

This slice consolidates numbered production variants 176, 177, 178, and 180 through 186 of `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan` into the canonical production class. The old numbered production files were removed, and their readable implementations now live as ordinary non-numbered variant helpers in `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`.

Direct B-tree tests and Application examples for those variants now call the canonical class through explicit variant entry methods such as `tableLeafFromDeleteResultNext176()` and `tableLeafFromDeleteResultNext186()`. Downstream production variants 187 through 189 keep their assigned numbered class names, but their direct base-plan references now point at the non-numbered canonical helper variants because the 184 through 186 production classes no longer exist.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext176Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext184Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext185Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext186Test.php` -> `10 test files, 6053 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Test.php` -> `3 test files, 2532 assertions, 0 failures`.
- `git diff --name-only --diff-filter=ACM -- 'lanes/libsqlite/*.php' 'lanes/libsqlite/src/*.php' 'lanes/libsqlite/tests/*.php' 'lanes/libsqlite/examples/*.php' | xargs -r -n1 php -l` -> no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan.php && php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Plan.php && php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php` -> no syntax errors.
- `for f in lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next{176,177,178,180,181,182,183,184,185,186}.php; do php "$f" >/dev/null || exit $?; printf 'PASS %s\n' "$f"; done` -> all ten examples passed.
- `git diff --check -- lanes/libsqlite` -> passed.

Dependency closure: no new support component is needed. This is a source consolidation only; behavior continues to reuse the existing native b-tree delete, freeblock, overflow, pointer-map, and current-source planning helpers.

Non-overlap: this only consolidates the assigned B-tree vacuum duplicate family range 176-186. It does not consolidate variants outside this range except for exact direct references in 187-189 needed after deleting numbered production base classes.
