# Gitoxide Config Include Malformed Bracket Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T124121Z`

Accepted base: `b46358aff7aa9b475bc4c01fea4fdbf8d07e53e1`

Upstream source truth:

- `gix-config/src/file/includes/mod.rs` routes `gitdir`, `gitdir/i`,
  `onbranch`, and `hasconfig:remote.*.url` conditions through
  `gix_glob::wildmatch` with `NO_MATCH_SLASH_LITERAL`.
- `gix-glob/src/wildmatch.rs` returns the non-match/abort path for malformed
  bracket classes. Unterminated classes and unsupported POSIX classes do not
  fall back to literal bracket matching.

Native PHP movement:

- `GitConfig::wildmatch()` now makes unmatched bracket classes impossible to
  match instead of treating `[` as a literal byte.
- Unsupported POSIX classes such as `[[:word:]]` now abort the character class
  match instead of matching the literal bracket text.
- `GitConfigTest.php` adds focused `gitdir`, `onbranch`, and
  `hasconfig:remote.*.url` includeIf coverage for unsupported POSIX and
  unclosed bracket classes.
- The WordPress config include fixture/example now proves malformed remote URL
  policies remain unloaded.

Focused evidence:

- Red-first probe before the implementation loaded a `gitdir:work[/` include
  for a literal `work[` directory and returned `string(3) "bad"`.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed
  `1 test files, 99 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `39 test files, 4583 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited 0.
- `git diff --check -- lanes/gitoxide` passed.

Dependency closure:

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and bounded byte-oriented glob matcher; no shared
dependency row or activation gate is proposed.

Non-overlap:

This extends the accepted config include escape, `**/` zero-component,
component-boundary, bracket-slash, byte-safe, and POSIX class slices with the
remaining malformed bracket abort behavior. It does not touch protocol,
transport, pack, reference, object database, index, sparse checkout, pathspec,
URL/refspec, or merge behavior. The old Gitoxide smart-HTTP rework notes are
stale for this slice because they target receive-pack redirect/status metadata
conflicts, not config include parity.
