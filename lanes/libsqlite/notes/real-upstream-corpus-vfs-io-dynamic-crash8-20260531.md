# real-upstream-corpus-vfs-io-dynamic crash8 handoff

- Base accepted HEAD: `68a3731675769814ce7d56857d9182ac7f8b3613`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/crash8.test`.
- Ported sections: `crash8-1.1` through `crash8-1.3`, `crash8-2.1`/`crash8-2.3`, `crash8-3.5` through `crash8-3.11`, `crash8-4.1`/`crash8-4.4`/`crash8-4.8` through `crash8-4.10`, and `crash8-5.1`/`crash8-5.2`.
- Behavior added: `SQLiteVfsIoDynamicPlan::crashHotJournalRecoveryProfile()` models hot-journal crash recovery branches for stale cache purge, persistent multi-header journals, suspect sector/page-size journal refusal, valid hot-journal replay, persistent multi-file master-journal truncation, and copied hot-journal integrity loops.
- Focused test growth: `SQLiteRealUpstreamCorpusVfsIoDynamicCrash8Test.php` adds `1001` TestRunner PASS cases and `27013` assertions.
- Non-overlap: this uses `crash8.test`, which was not present in current VFS/IO dynamic tests; it does not repeat existing `io.test`, `ioerr*`, `journal2`, `delete_db`, `8_3_names`, WAL, rollback commit, sync, lock-state, file-writer, or savepoint rollback clusters.
- Dependency closure: no new support component is needed; the bounded profile reuses existing PHP VFS/pager planning surfaces and cites real hydrated upstream crash tests.
