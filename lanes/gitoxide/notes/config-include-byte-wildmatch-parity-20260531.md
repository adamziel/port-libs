# Gitoxide Config Include Byte Wildmatch Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T103752Z`

Accepted base: `f9d9e6312c63dfc0751eedbcf238e9e6c2d6e7da`

Upstream source truth:

- `gix-glob/src/wildmatch.rs` matches `BStr` byte slices, not UTF-8 strings.
  `?`, `*`, `**`, and bracket classes operate on raw bytes while preserving
  the `NO_MATCH_SLASH_LITERAL` boundary used by config includes.
- `gix-config/src/file/includes/mod.rs` passes `gitdir`, `onbranch`, and
  `hasconfig:remote.*.url` conditions through that byte-oriented matcher.
  Conditional includes therefore still match legacy malformed path, ref, or
  remote URL bytes when the pattern allows them.

Native PHP movement:

- `GitConfig::wildmatch()` no longer enables PCRE UTF-8 mode, so conditional
  include matching remains byte-safe for malformed legacy bytes.
- `GitConfigTest.php` adds focused checks for malformed-byte `gitdir`,
  `onbranch`, and `hasconfig:remote.*.url` conditions.
- The WordPress config include fixture/example now includes a legacy-byte remote
  policy that is loaded through the same byte-safe `hasconfig` boundary.

Focused evidence:

- Red-first probes before the implementation returned `base`/`NULL` for
  `gitdir:legacy-?/`, `onbranch:release-?`, and
  `hasconfig:remote.*.url:.../legacy-?.git` when the target contained `\xFF`.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed
  `1 test files, 68 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `39 test files, 4261 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited 0.
- `git diff --check -- lanes/gitoxide` exited 0.

Dependency closure:

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and bounded glob matcher; no shared dependency row or
activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf escape, double-star, and
bracket-slash slices with byte-safe wildcard matching. It does not touch
protocol, transport, pack, reference, object database, index, sparse checkout,
pathspec, URL/refspec, or merge behavior. The old Gitoxide smart-HTTP rework
notes are stale for this slice because they target receive-pack redirect/status
metadata conflicts, not config include parity.
