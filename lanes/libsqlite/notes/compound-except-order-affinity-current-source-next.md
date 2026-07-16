# Compound Select EXCEPT Order Affinity Current Source Next

Status: consolidated numbered method wrappers for compound SELECT EXCEPT with final ORDER BY storage-class affinity across current/next sources.

This slice preserves `SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan` behavior while removing the generated numbered public and private method names. The direct focused test and Application copied `wp_options` smoke were renamed to stable unsuffixed filenames and now call the canonical `compare()` entrypoint.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundExceptOrderAffinityCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundExceptOrderAffinityCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-compound-except-order-affinity-current-source-next.php`
- `php lanes/libsqlite/examples/application-compound-except-order-affinity-current-source-next.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: no `phpPass` or mapped-coverage movement; this is a production method-name consolidation that preserves the existing focused assertions.

Non-overlap: avoids functional changes to accepted compound row composition, compound EXCEPT/window affinity, compound LIMIT/window affinity, compound collation/window, SQL expression ORDER BY, GROUP BY text, subquery text, encoding Unicode GLOB, JSON table source/constraint, WAL/VFS/B-tree current-source clusters. This pass only consolidates one remaining numbered compound method family.

Dependency closure: no new support component is needed; this reuses lane-local `SQLiteSelectSql`, `SQLiteSelectCompound`, and `SQLiteSelectResult` behavior.
