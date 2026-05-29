2026-05-29 - consolidate final B-tree vacuum numbered methods, twenty-fourth pass

Scope:
- Migrated the remaining direct WordPress B-tree vacuum example callers for
  `tableLeafFromDeleteResultNext154()` through `tableLeafFromDeleteResultNext216()`
  to the existing stable descriptive production entrypoints on
  `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`.
- Left the focused tests on their already-consolidated canonical methods and
  verified the examples still exercise the same numbered scenarios without
  requiring numbered production method names.

Verification:
- `php -l` passed for all 35 changed example PHP files.
- `php tools/run-tests.php` for the matching 35 B-tree vacuum focused test files
  passed: `35 test files, 21330 assertions, 0 failures`.
- Changed WordPress examples were run with `--self-test`; all exited 0. Older
  JSON-only examples emitted their summary JSON, and examples with explicit
  self-test checks printed their passed line.
- `rg -n "tableLeafFromDeleteResultNext[0-9]+" lanes/libsqlite/src lanes/libsqlite/tests lanes/libsqlite/examples`
  returned no matches.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component is needed; this is caller consolidation over the
  existing canonical B-tree vacuum pointer-map/freeblock implementation.
