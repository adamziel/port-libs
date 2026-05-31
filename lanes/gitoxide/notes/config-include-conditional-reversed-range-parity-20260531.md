# Gitoxide Config Conditional Include Reversed Range Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260531T231006Z`

Accepted base: `b77f76b33ac877becd8fb58514949f334f0fbc0d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  routes `gitdir`, `gitdir/i`, `onbranch`, and
  `hasconfig:remote.*.url` include conditions through `gix_glob::wildmatch`
  with `NO_MATCH_SLASH_LITERAL`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  evaluates bracket classes byte-by-byte instead of delegating malformed or
  reversed ranges to a regex engine. A reversed range such as `[z-a]` matches
  the already-seen start byte without emitting a runtime warning, and
  `IGNORE_CASE` lowercases pattern bytes before POSIX class-name matching.

Native PHP movement:

- `GitConfig::wildmatch()` now compiles bracket classes into explicit byte
  sets, preserving the existing slash guard while avoiding PCRE invalid-range
  warnings for reversed ranges.
- `GitConfigTest.php` adds includeIf checks for reversed ranges and negated
  reversed ranges across `onbranch`, `gitdir`, `gitdir/i`, and
  `hasconfig:remote.*.url`, plus uppercase POSIX class names under
  `gitdir/i`.
- The WordPress config include fixture/example now records a deployment
  remote policy that loads for the reversed-range start byte and rejects a
  separate middle-byte reversed range without warning.

Focused evidence:

- Red-first probe before the implementation: a one-off
  `GitConfig::fromFile()` load with
  `includeIf "gitdir:worktree/[z-a].git"` returned the base value but emitted
  `preg_match(): Compilation failed: range out of order in character class`.
- `php -l lanes/gitoxide/src/GitConfig.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 168 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 6122
  assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exits `0`.
- `git diff --check -- lanes/gitoxide`: exits `0`.

Dependency closure:

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and byte-oriented wildmatch compiler; no shared
dependency row or activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf escape, double-star,
bracket-slash, POSIX class, malformed bracket, byte-safe, optional-prefix,
backslash-gitdir, and symlink-gitdir slices with the remaining reversed-range
and icase POSIX class-name boundary. It does not touch protocol, transport,
pack, reference, object database, index, sparse checkout, pathspec,
URL/refspec, or merge behavior. The old Gitoxide smart-HTTP rework notes are
stale for this slice because they target receive-pack redirect/status metadata
conflicts, not config include parity.
