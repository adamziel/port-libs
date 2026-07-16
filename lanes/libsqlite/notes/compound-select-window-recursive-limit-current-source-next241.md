# Compound SELECT Window Recursive LIMIT Current Source Next241

Status: focused PHP behavior growth for current-source compound SELECTs where
a yielded recursive/window result page must prove the final current and next
rows still match the source-generation seal before a next-source cursor can
resume.

This slice adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`,
layered on the accepted next238 source-generation seal. The new surface is a
resume-admission receipt over current final rows, next final rows, and the
recursive/window/LIMIT boundary. A resume cursor must acknowledge all three
tokens before it can reuse the next-source cursor.

Application path:
`application-compound-select-window-recursive-limit-current-source-next241.php`
models a copied `wp_options` import preview where a new autoloaded plugin row
moves across a compound `UNION`/`EXCEPT` final `LIMIT/OFFSET` page while
recursive dependency rows keep their window rank.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext241Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next241.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext241Test.php`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next241.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +77` from the new focused test file.
Mapped coverage remains unchanged because this is current-source PHP behavior
over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next226 sum/count EXCEPT+INTERSECT behavior,
next229 dense-rank source tokens, next232 page acknowledgements, next235
promotion barriers, next238 source-generation/final-boundary acknowledgements,
suite next241 veryquick evidence, row-value/window RETURNING, trigger
recursive UPSERT, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, and
suite evidence handoffs. The narrower behavior is final-row resume admission
after the accepted source-generation seal.

Dependency closure: no new support component is needed; this reuses lane-local
SELECT SQL, recursive CTE tracing, compound SELECT, window rank execution, and
current-source cursor metadata.
