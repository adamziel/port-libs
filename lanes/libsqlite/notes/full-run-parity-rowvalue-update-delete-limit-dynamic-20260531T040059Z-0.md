# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Added focused generic coverage for row-value `UPDATE` and `DELETE` statements whose tuple-source subqueries use `ORDER BY` with dynamic `LIMIT`, `OFFSET`, comma-limit, zero-limit, and negative-limit windows.

Upstream source cues:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/delete.test`

Verification target:
- `SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php` covers twenty positive dynamic windows and two malformed limit forms against the native PHP row-value `UPDATE/DELETE RETURNING` executor.

Dependency closure: no new support component is needed; this reuses lane-local row-value tuple-source materialization, native `UPDATE/DELETE RETURNING`, ordered subquery limit parsing, comma-limit parsing, and generic app-settings row fixtures.

Non-overlap: this does not add domain-specific API names and avoids the accepted row-value savepoint retry, negative-limit-only, DISTINCT tuple, compound tuple-source, trigger/RETURNING, WAL/VFS, JSON, planner, and B-tree clusters. The slice is a generic dynamic LIMIT/OFFSET parity matrix over the already-native row-value DML executor.
