# Gitoxide Config Include Quoted Path Comment Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T155907Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/parse/from_bytes/mod.rs`
  stops unquoted values at `;` and `#`, then trims trailing ASCII whitespace
  before emitting the raw value event.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/value/normalize.rs`
  strips enclosing quotes and unescapes quoted config values after parsing.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  gathers `include.path` values for direct includes and matched `includeIf`
  sections through the same parsed-value path before include resolution.

## Native Movement

- `GitConfig::parseValue()` now strips inline comments before trimming both
  leading and trailing surrounding whitespace, so `path = "inc" ; comment`
  normalizes to `inc` instead of leaving a trailing space after the closing
  quote.
- `GitConfigTest.php` adds direct `include.path`, `onbranch`, `gitdir`, and
  `hasconfig:remote.*.url` coverage for quoted include paths followed by `;`
  or `#` comments.
- The WordPress config include fixture/example now expose a
  `quotedCommentPathPolicy` loaded through a quoted conditional include path
  with a trailing inline comment.

## Evidence

- Red-first focused probe before the implementation:
  `GitConfig::fromFile()` returned `NULL` for a matching
  `includeIf "onbranch:deploy/"` path written as `"inc" ; comment`.
- Baseline focused `GitConfigTest.php` before the slice:
  `1 test files, 311 assertions, 0 failures`.
- `php -l lanes/gitoxide/src/GitConfig.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 318 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 10081
  assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exited `0`.
- `git diff --check -- lanes/gitoxide`: passed.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
Git config parser, include resolver, conditional include matcher, and
wildmatch helpers. No live provider, Git binary, credential store, shared
dependency row, or full upstream Cargo workspace runner was used.

## Non-Overlap

This is additive to accepted config include work for direct include casing,
optional prefixes, path interpolation, named-user interpolation, gitdir
realpath fallback, max-depth handling, byte-safe wildmatch, bracket/POSIX
condition matching, and malformed POSIX resume behavior. It is limited to the
remaining parsed value normalization boundary for quoted include paths followed
by inline comments.
