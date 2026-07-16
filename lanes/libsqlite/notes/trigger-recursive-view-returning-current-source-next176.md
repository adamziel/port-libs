# trigger-recursive-view-returning-current-source-next176

Status: focused PHP behavior growth for recursive INSTEAD OF view trigger
`RETURNING` streams at the current-source/next-source boundary.

This slice adds a current-page acknowledgement gate on top of the accepted
next171-173 recursive view RETURNING cursor work. The next view source is
admitted only when the current `RETURNING` pages are acknowledged as a
duplicate-free contiguous prefix. Gapped or duplicate acknowledgements keep the
next source fenced even when its rows are already prepared.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext176Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next176.php --self-test`

Expected dashboard movement: `phpPass +62` from the new focused test file.
Mapped upstream coverage is unchanged; this is current-source PHP behavior over
already mapped trigger/view/RETURNING inventory.

Non-overlap: avoids accepted next171 cursor close gating, next172 recursive
view drain behavior, next173 resume-token source admission, batch161
trigger recursive/view RETURNING behavior, row-value RETURNING savepoint
clusters, VFS/WAL/B-tree/JSON/encoding/planner clusters, and suite evidence
handoffs. The narrower behavior is contiguous page acknowledgement validation
for the already materialized current `RETURNING` cursor before next-source
visibility.

Dependency closure: no new support component is needed; this reuses native
recursive view trigger, RETURNING cursor pagination, and current/next source
signature machinery.
