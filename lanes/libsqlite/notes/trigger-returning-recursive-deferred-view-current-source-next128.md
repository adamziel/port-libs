# trigger-returning-recursive-deferred-view-current-source-next128

Adds `SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan`, a
bounded current-source behavior slice for recursive trigger `RETURNING` rows
combined with deferred foreign-key checking and view materialization.

The new surface is intentionally narrower than accepted next118/next126
recursive trigger RETURNING work: current recursive RETURNING rows are drained,
a Application-style autoloaded-options view is materialized from that current
source, and only then does deferred FK validation decide whether the next source
can advance or must roll back to the original current source.

Application smoke:

- `php lanes/libsqlite/examples/application-trigger-returning-deferred-view-current-source-next128.php --self-test`
- Result: `application-trigger-returning-deferred-view-current-source-next128 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNext128Test.php`
- Result: `1 test files, 54 assertions, 0 failures`

Non-overlap: avoids accepted recursive UPSERT RETURNING current/next next118
and next126, deferred FK cascade trigger next116, trigger recursive deferred FK
next111/next121, view-trigger savepoint next123, schema view/trigger reparse
next125, row-value RETURNING next117/next126, WAL/pager/B-tree/JSON/encoding
current-source clusters, and release-runner evidence work. The added behavior
is the ordering of view-current-source materialization between current
RETURNING drain and deferred FK next-source admission.

Dependency closure: no new support component is needed; this reuses the
existing native PHP recursive trigger RETURNING, bounded view projection, and
deferred foreign-key validation primitives.
