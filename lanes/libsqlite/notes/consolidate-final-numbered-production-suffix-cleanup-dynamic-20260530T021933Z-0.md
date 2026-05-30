# Consolidate final numbered upstream veryquick suffix names

This cleanup removes the remaining numbered production method names for the
current-source veryquick shard next382/next383 evidence entries in
`SQLiteUpstreamSuiteEvidence`.

- The shard admission entry point is now
  `upstreamVeryquickShardCurrentSourceVdbeLiteralBranchAdmission()`.
- The shard follow-up entry point is now
  `upstreamVeryquickShardCurrentSourceVdbeLiteralBranchFollowup()`.
- The direct focused tests were renamed to match the stable production method
  names.

Observable receipt keys, status strings, countability keys, dependency text,
and next-gate wording still preserve the existing next382/next383 shard
identities so downstream evidence consumers do not lose historical keys.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceVdbeLiteralBranchAdmissionTest.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceVdbeLiteralBranchFollowupTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceVdbeLiteralBranchAdmissionTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceVdbeLiteralBranchFollowupTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceVdbeLiteralBranchAdmissionTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceVdbeLiteralBranchFollowupTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
method/test naming consolidation over existing upstream-suite evidence
admission behavior.

Non-overlap: this only touches the upstream veryquick shard next382/next383
direct method names and test filenames. It does not change pager, B-tree, JSON,
STAT4 planner, compound SELECT, WAL, VFS, pragma, trigger, encoding, or root-gate
behavior.
