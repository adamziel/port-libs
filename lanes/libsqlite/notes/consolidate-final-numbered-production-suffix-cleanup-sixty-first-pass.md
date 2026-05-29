# Consolidation Sixty-First Pass

Scope: `consolidate-final-numbered-production-suffix-cleanup-sixty-first-pass`.

Changed the direct compound window recursive affinity cursor family from the
numbered production helper names `pageNext147`, `sliceNext147`,
`signatureNext147`, and `validateCursorNext147` to stable descriptive
cursor helper names on `SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan`.
The direct focused test and WordPress smoke were renamed and updated to use the
canonical helper.

Evidence to run for handoff:

- `php -l` on changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveAffinityCurrentSourceCursorTest.php`.
- `php lanes/libsqlite/examples/wordpress-compound-window-recursive-affinity-current-source-cursor.php --self-test`.
- `git diff --check -- lanes/libsqlite`.

Dependency closure: no new support component is needed; this pass only renames
the existing native PHP cursor-paging helper surface.

Non-overlap: this is a suffix consolidation slice only. It does not change
compound SELECT behavior and keeps the exact user-named suffix absent
from the touched production/test/example family.
