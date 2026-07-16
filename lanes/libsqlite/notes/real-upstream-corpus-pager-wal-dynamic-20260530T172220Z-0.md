# real-upstream-corpus-pager-wal-dynamic-20260530T172220Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test`.

Ported behavior:

- `walpersist-1.0` through `walpersist-1.11`: WAL sidecars exist while WAL is open, default close deletes sidecars, and `file_control_persist_wal` toggles close-time WAL/SHM retention.
- `walpersist-2.1` through `walpersist-2.3`: persistent WAL plus a non-negative `journal_size_limit` truncates the retained WAL file to zero bytes while preserving database readability.
- `walpersist-3.1` through `walpersist-3.4`: autocheckpointed persistent WAL is retained but truncated to zero on close.
- `walpersist-4.1`: moving through `TRUNCATE`, `MEMORY`, `WAL`, then `PERSIST` clears persistent-WAL state once the connection leaves WAL mode.

Focused assertion movement: `SQLiteWalPersistCorpusTest.php` adds 79 focused assertions over real upstream WAL-persistence behavior. No mapped denominator row is claimed.

Dependency closure: no new support component is needed. The slice reuses existing bounded VFS/file-control and WAL sidecar concepts and adds a small generic PHP planner for persistent WAL close decisions.

Root harness: not run - isolated micro-slice.
