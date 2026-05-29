# trigger-upsert-recursive-view-current-source-next148

## Scope

Adds focused current-source behavior for `INSERT ... ON CONFLICT DO UPDATE RETURNING`
through an `INSTEAD OF` view trigger when recursive update triggers upsert dependent
WordPress option rows. The slice is intentionally narrower than accepted trigger
RETURNING/view work: it covers recursive side effects and confirms the current
source yields diagnostics before the next source is admitted.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerUpsertRecursiveViewCurrentSourceNext148Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertRecursiveViewCurrentSourceNext148Test.php`
- `php lanes/libsqlite/examples/wordpress-trigger-upsert-recursive-view-current-source-next148.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap

Avoids accepted next144 trigger UPSERT RETURNING view source gating by adding
recursive trigger side-effect rows (`siteurl -> home -> rewrite_rules`) and
current-source savepoint retention. It does not repeat deferred FK, trigger
RETURNING view rollback, row-value UPSERT, or schema reparse surfaces.

## Dependency closure

No new support component is needed. The slice reuses lane-local PHP row-array
execution helpers and adds a bounded native plan for recursive view-trigger
UPSERT diagnostics.
