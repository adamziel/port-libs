# compound-select-except-order-affinity-current-source-next138

Status: focused PHP behavior growth for compound SELECT EXCEPT with final ORDER BY storage-class affinity across current/next sources.

This slice adds `SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan` and a WordPress copied `wp_options` smoke. It verifies that EXCEPT removal uses the left arm's NOCASE collation and SQLite storage-class equality, while the final compound ORDER BY sorts the surviving rowset by result-column storage class, explicit NULLS placement, and NOCASE name ordering after current-source changes.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundExceptOrderAffinityCurrentSourceNext138Test.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundExceptOrderAffinityCurrentSourceNext138Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-except-order-affinity-current-source-next138.php`
- `php lanes/libsqlite/examples/wordpress-compound-except-order-affinity-current-source-next138.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +61` from the new focused test file. `benchmarkDenominator.mapped` remains `606 / 1589`; this is current-source PHP behavior over already mapped compound SELECT/ORDER BY/affinity inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted compound row composition, compound EXCEPT/window affinity next133, compound LIMIT/window affinity next137, compound collation/window next136, SQL expression ORDER BY, GROUP BY text, subquery text, encoding Unicode GLOB, JSON table source/constraint, WAL/VFS/B-tree current-source clusters. The new surface is final compound ORDER BY storage-class/NOCASE ordering after EXCEPT removes rows at the current-source to next-source boundary.

Dependency closure: no new support component is needed; this reuses lane-local `SQLiteSelectSql`, `SQLiteSelectCompound`, and `SQLiteSelectResult` behavior.
