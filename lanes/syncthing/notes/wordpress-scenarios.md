# syncthing WordPress Scenario

Resumable media/content synchronization for local-first WordPress and Playground folders.

## Current Native Slice

Native scanner-style content blocks now match Syncthing's `lib/scanner/blocks_test.go`
fixtures for empty files, SHA-256 block hashes, exact coverage checks, block-list
hashing, optional hash validation, and upstream per-file block size selection.
Protocol version vectors now cover update/merge/compare ordering, and FileInfo
conflict decisions now cover invalid flag handling, block lineage conflicts,
winner ordering, and tombstone construction. FileInfo equivalence now maps a
focused slice of upstream `lib/protocol/bep_fileinfo_test.go`: block-list
equality shortcuts, local invalid flag equivalence, permission/block ignore
options, symlink target checks, modification time windows, and Unix ownership
matching by numeric IDs or resolved names.

The example in `examples/wordpress-media-resume.php` shows how WordPress or
Playground import tooling can resume a partially synchronized upload by trusting
only blocks whose hashes still match the local bytes, then continuing at the
next byte offset. The FileInfo slice gives that same workflow a native boundary
for concurrent media edits, deleted uploads, and remote-invalid entries before a
higher-level sync planner chooses the final WordPress object state.
`examples/wordpress-fileinfo-equivalence.php` shows the adjacent decision:
scanner-only metadata noise can be treated as equivalent while actual media byte
changes still force a sync decision.

## Next Task

Port index update message fixtures, wire-format path normalization, or request
validation behavior from focused upstream protocol tests.
