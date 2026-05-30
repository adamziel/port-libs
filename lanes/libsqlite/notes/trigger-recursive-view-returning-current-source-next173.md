# trigger-recursive-view-returning-current-source-next173

Status: focused PHP behavior growth for recursive view `RETURNING` current-source cursor admission.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Plan`, a wrapper over the accepted next167 recursive view RETURNING plan. It models the narrower current-source boundary where a prepared next view source remains fenced until the current-source RETURNING cursor has exhausted every page and the resume source signature still matches the current view/trigger source.

Application smoke: `application-trigger-recursive-view-returning-current-source-next173.php` covers copied `wp_options` import behavior where the first current RETURNING page is drained, recursive current rows are still pending, and the next import view source remains blocked even though next-source rows have already been prepared.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Test.php`
  - `1 test files, 54 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext173Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next173.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next173.php --self-test`
  - `application-trigger-recursive-view-returning-current-source-next173 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta:

- `phpPass`: `77680 -> 77734` from 54 focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged at `612 / 1589`; this is additive current-source PHP behavior over the existing trigger recursive/view RETURNING surface, not a newly hydrated upstream inventory row.

Non-overlap:

This avoids accepted trigger recursive/view RETURNING next167 page-yield coverage, row-value update/delete RETURNING savepoint behavior, trigger upsert/view next149, savepoint/view trigger behavior, deferred FK trigger RETURNING, schema view/trigger reparse, and all B-tree/WAL/pager/JSON/encoding/PRAGMA/planner accepted clusters. The new behavior is specifically partial current RETURNING cursor drain plus resume-source-token admission for a prepared next view source.

Dependency closure:

No new support component is needed. The slice reuses lane-local recursive view RETURNING source barriers and page-yield primitives.

Next task:

Continue trigger closure with a distinct unhandled trigger/FK/view behavior, or pivot to SQL/WAL/B-tree/JSON/encoding buckets if trigger surfaces are already queued.
