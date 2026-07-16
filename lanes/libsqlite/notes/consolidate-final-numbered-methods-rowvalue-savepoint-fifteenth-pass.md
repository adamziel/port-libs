# Row-value savepoint numbered method consolidation fifteenth pass

This consolidation pass removes the remaining numbered `executeNextNN` public
entry points and matching private helper suffixes from
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`.

The behavior remains in the canonical production class under descriptive
savepoint/rollback names, and direct row-value savepoint tests/examples now
call those stable methods. No new support component is needed; this reuses the
existing row-value SQL executor and savepoint row-array fixtures.

