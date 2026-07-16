# compound-select-recursive-affinity-current-source-next120

This slice wires recursive CTE `UNION` duplicate tracking through the same compound SELECT row-value key used by `UNION` / `INTERSECT` / `EXCEPT`. The behavior matches SQLite set comparison for the covered current-source edge: integer and real values compare as duplicate numeric rows, while text, BLOB, and NULL retain SQLite compound distinctness.

Application path: `application-compound-recursive-affinity-current-source-next120.php` models a copied `wp_options` repair/import query where a recursive numeric sequence is compounded with current option rows. It proves the generated `1.0` row is skipped as a recursive `UNION` duplicate of anchor `1`, and the outer current-source `UNION` also keeps a single left representative.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveAffinityCurrentSourceNext120Test.php`
- `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-compound-recursive-affinity-current-source-next120.php --self-test`
- `application-compound-recursive-affinity-current-source-next120 self-test passed`

Dependency closure: no new support component is needed. The slice reuses existing SELECT SQL, recursive CTE, compound SELECT, BLOB, projection, result, and query helpers.

Non-overlap: this avoids accepted next117 recursive compound LIMIT/OFFSET and positional output-name coverage, accepted compound EXCEPT/INTERSECT affinity tests, accepted expression ORDER BY, JSON table source/cursor/constraint work, VFS/WAL/B-tree application clusters, and suite-runner evidence. The new surface is specifically recursive CTE queue de-duplication using compound numeric equality before the recursive/current-source compound boundary.

Next task: continue compound SELECT work only on a non-overlapping parser/executor edge, or pivot to WAL/pager, JSON planner, B-tree, encoding, or suite blocker work with focused assertion growth.
