# Gitoxide Config Include Wildmatch Recursion Guard Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T183440Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  sets `RECURSION_LIMIT` to `64`; recursive wildmatch attempts that reach that
  depth return `RecursionLimitReached`, and the public `wildmatch()` helper
  reports no match for that result.
- The same file recurses for path-component `**/` matches in
  `NO_MATCH_SLASH_LITERAL` mode, including the zero-component branch before
  consuming path bytes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  routes `gitdir`, `gitdir/i`, `onbranch`, and
  `hasconfig:remote.*.url` includeIf conditions through the same slash-aware
  `gix_glob::wildmatch` behavior.

## Native Movement

- `GitConfig.php` now rejects wildmatch patterns containing 64 consecutive
  zero-component path `**/` branches before translating them to PHP regexes.
- `GitConfigTest.php` locks the parity boundary at 63 matching recursive
  branches and 64 rejected branches for `onbranch`, `gitdir`, and
  `hasconfig:remote.*.url` includeIf conditions.
- The WordPress config include fixture/example now proves deployment branch
  include selection keeps a 63-level recursive include policy and rejects the
  64-level recursion-limit policy.
- `UPSTREAM_TEST_MANIFEST.json` moves conservative mapped coverage
  `1812 -> 1813 / 2886` if accepted, and `lane-status.json` records
  `10501 -> 10511` PHP assertions for this isolated slice.

## Evidence

- Red-first `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  before the production change: `1 test files, 229 assertions, 2 failures`.
- `php -l` on changed PHP files: no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 328 assertions, 0 failures`.
- `TMPDIR=/home/claude/port-libs/.tmux-team/tmp php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 10511 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exited `0`.
- JSON validation for `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and
  `lanes/gitoxide/lane-status.json`: passed.
- `git diff --check -- lanes/gitoxide`: passed.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
Git config parser, include resolver, and byte-oriented wildmatch compiler. No
live provider, credential store, Git binary, extra support row, or full
upstream Cargo workspace runner was used.

## Non-Overlap

This is additive to accepted config include work for include recursion depth,
double-star component boundaries, malformed bracket and POSIX-class handling,
POSIX blank boundaries, reversed ranges, escaped hyphens, dot-slash missing
paths, trailing backslashes, byte-safe wildmatch, path interpolation, symlink
and realpath gitdirs, environment pairs, directive casing, optional prefixes,
and max-depth include loading. It is limited to the remaining gix-glob
wildmatch recursion guard behavior as observed through conditional include
matching, and it does not touch protocol, transport, reference, pack, object,
sparse-checkout, or merge behavior.
