# Row-value Yield Returning Savepoint Consolidation

This consolidation removes the remaining numbered production-facing labels from
`SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan`.

The canonical helper now reports stable savepoint, phase, receipt,
dependency-closure, dependency, and non-overlap keys while preserving the same
row-value UPDATE/DELETE RETURNING savepoint rollback behavior. The direct
focused test and WordPress smoke were migrated to the stable names.

Dependency closure: no new support component is needed; this is a naming
consolidation over the existing native row-value UPDATE/DELETE RETURNING
executor and row-array savepoint images.
