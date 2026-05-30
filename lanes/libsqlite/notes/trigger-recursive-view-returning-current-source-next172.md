# Trigger Recursive View RETURNING Current Source Next172

- Added `SQLiteTriggerRecursiveViewReturningCurrentSourceNext172Plan` for recursive `INSTEAD OF` view-trigger batches where current-source `RETURNING` rows drain before a next view/trigger source is admitted.
- Focused behavior covers current-source pinning, optional next-source admission, recursive child rows, `RETURNING` old/new/event/depth/trigger-source projection, statement/recursive change accounting, and malformed view/source guards.
- Application smoke: copied `wp_options` import through a recursive view trigger keeps current source visible and suppresses attempted next-source `RETURNING` rows until reprepare/admission.
- Non-overlap: avoids accepted next147 recursive table-trigger savepoint rollback, next149 non-recursive view UPSERT source pinning, accepted DML trigger RETURNING conflict handling, deferred FK cascade triggers, and schema trigger/view invalidation batches.
- Dependency closure: no new support component needed; this reuses lane-local row-array trigger/view RETURNING modeling.
