# Gitoxide Config Conditional Include POSIX Class Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T111205Z`

Accepted base: `729105b48b26aa61ef0db4b008592ded7b7410d2`

Upstream source truth:

- `gix-config/src/file/includes/mod.rs` routes `gitdir`, `onbranch`, and
  `hasconfig:remote.*.url` includeIf conditions through
  `gix_glob::wildmatch` with `NO_MATCH_SLASH_LITERAL`.
- `gix-glob/src/wildmatch.rs` parses POSIX bracket classes inside character
  classes, including `[:alpha:]`, `[:digit:]`, negated classes, ASCII class
  ranges, and the slash-literal rejection that applies after class matching.

Native PHP movement:

- `GitConfig::wildmatch()` now finds bracket-class ends while skipping nested
  POSIX class terminators, translates supported POSIX classes into byte-safe
  PCRE ranges, and keeps the existing slash lookahead guard for config path
  matching.
- `GitConfigTest.php` adds focused includeIf checks for POSIX classes across
  `onbranch`, `gitdir`, `gitdir/i`, and `hasconfig:remote.*.url`.
- The WordPress config include fixture/example now includes a
  `site-[[:digit:]]` remote policy loaded through `hasconfig`.

Focused evidence:

- Red-first probe before the implementation returned `base` and emitted a
  `preg_match()` character-class warning for
  `onbranch:deploy/[[:alpha:]]ite` against `refs/heads/deploy/site`.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed
  `1 test files, 81 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `39 test files, 4368 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited 0.

Dependency closure:

No new support component is needed. This slice reuses the native Git config
parser, include resolver, and bounded glob matcher; no shared dependency row or
activation gate is proposed.

Non-overlap:

This extends the accepted config include/includeIf escape, double-star,
bracket-slash, and byte-safe wildmatch slices with POSIX class parsing in
conditional include globs. It does not touch protocol, transport, pack,
reference, object database, index, sparse checkout, pathspec, URL/refspec, or
merge behavior. The old Gitoxide smart-HTTP rework notes are stale for this
slice because they target receive-pack redirect/status metadata conflicts, not
config include parity.
