# Gitoxide Config Include Malformed POSIX Resume Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T143814Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  treats malformed POSIX-class openers inside bracket classes, such as
  `[[:digit]`, as resumed byte-class matching rather than aborting the whole
  wildcard match.
- The same file keeps `[[::]ab]` as a non-match, so the resumed behavior stays
  distinct from malformed empty POSIX class syntax.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  routes `gitdir`, `onbranch`, and `hasconfig:remote.*.url` conditions through
  `gix_glob::wildmatch` with slash-aware matching.

## Native Movement

- `GitConfigTest.php` now locks down malformed POSIX-class resume behavior for
  `includeIf "gitdir:..."`, `includeIf "onbranch:..."`, and
  `includeIf "hasconfig:remote.*.url:..."`.
- The WordPress config include fixture/example now records a deployment remote
  URL policy where `site-[[:digit]ab].git` matches the stored
  `site-[ab].git` URL, while `site-[[::]ab].git` remains rejected.
- No production code change was needed; the existing native wildmatch compiler
  already had the upstream-compatible byte-class resume behavior.

## Evidence

- Baseline focused `GitConfigTest.php` before the slice: `1 test files, 298
  assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 311 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exited `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 9848
  assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`: passed.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
Git config parser, include resolver, and byte-oriented wildmatch compiler. No
live provider, Git binary, credential store, extra support row, or full
upstream Cargo workspace runner was used.

## Non-Overlap

This is additive to accepted config include work for malformed bracket aborts,
unknown POSIX class aborts, POSIX blank boundaries, reversed ranges, escaped
hyphens, trailing backslashes, byte-safe wildmatch, path interpolation,
symlink and realpath gitdirs, environment pairs, directive casing, optional
prefixes, and max-depth handling. It is limited to the remaining malformed
POSIX-class resume behavior as observed through conditional include matching.
