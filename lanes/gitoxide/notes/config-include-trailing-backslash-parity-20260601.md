# Gitoxide Config Include Trailing Backslash Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T001557Z`

Accepted base: `ab6bad81fab6df83f5e328b75e0bab2d9ce26b88`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  routes `gitdir`, `gitdir/i`, `onbranch`, and
  `hasconfig:remote.*.url` include conditions through
  `gix_glob::wildmatch` with `NO_MATCH_SLASH_LITERAL`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  treats a backslash as an escape byte and returns `NoMatch` when the pattern
  ends with a dangling backslash. The PHP matcher previously quoted that final
  byte as a literal.

Native PHP movement:

- `GitConfig::wildmatch()` now returns `false` for a terminal backslash escape
  before compiling the matcher regex.
- `GitConfigTest.php` adds conditional include checks for dangling-backslash
  patterns across `onbranch`, `gitdir`, and `hasconfig:remote.*.url`.
- The WordPress config include fixture/example now verifies that a deployment
  remote URL ending in a literal backslash does not load a policy from an
  invalid dangling-backslash includeIf glob.

Focused evidence:

- Red-first focused test before the matcher fix failed:
  `conditional include trailing backslash globs abort like gix wildmatch`
  returned `override-value` instead of `base-value`.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 173 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 6458
  assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exits `0`.

Dependency closure:

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and byte-oriented wildmatch compiler; no shared
dependency row or activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf escape, double-star,
bracket-slash, POSIX class, malformed bracket, byte-safe, optional-prefix,
backslash-gitdir, symlink-gitdir, and reversed-range slices with the remaining
dangling-escape boundary from `gix_glob::wildmatch`. It does not touch
protocol, transport, pack, reference, object database, index, sparse checkout,
pathspec, URL/refspec, or merge behavior. The old Gitoxide smart-HTTP rework
notes are stale for this slice because they target receive-pack redirect/status
metadata conflicts, not config include parity.
