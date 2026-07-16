# real-upstream-corpus-vfs-io-dynamic-20260531T041042Z-0

- Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/sysfault.test`.
- Ported behavior: `sysfault.test` `2.setup` and `2.1`, where a single transient `EINTR` from Unix VFS syscalls (`open`, `ftruncate`, `close`, `read`, `pread`, `pread64`, `write`, `fallocate`) must be retried without changing transaction results, attached database updates, post-delete rows, or integrity.
- Focused PHP coverage: `SQLiteRealUpstreamCorpusVfsSysfaultTransientEintrDynamicTest.php` adds 1,001 TestRunner cases over 8 syscalls, 4 journal modes, 5 chunk-size hints, 5 large payload sizes, attached/non-attached aux writes, and malformed-input guards.
- Non-overlap: avoids accepted VFS atomic admission, quick-balance, mmap, WAL/SHM, short-name sidecar, file-writer, lock-state, process-lock, rollback-commit, and syscall registry/chunk/temp/close helper slices by covering `sysfault.test` transient `EINTR` retry semantics.
- Dependency closure: no new support component needed; this reuses the existing generic VFS I/O dynamic profile helper and models the real upstream Unix VFS syscall retry contract.
