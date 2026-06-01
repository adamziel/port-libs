# Gitoxide Config Include Gitdir Realpath Fallback Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T132650Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  calls `gix_path::realpath()` for a `gitdir:` condition when the raw gitdir
  path did not match the include pattern.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-path/src/realpath.rs`
  resolves `.` and `..` components without requiring every path component to
  exist. That keeps planned or not-yet-created git directories matchable after
  normalization.

## Ported Behavior

- `GitConfig::gitDirMatches()` now falls back to a Gitoxide-like path
  normalization when PHP `realpath()` cannot resolve the whole gitdir path.
- The fallback preserves the existing filesystem-backed `realpath()` behavior
  for real paths and adds non-existing component normalization for `.` and
  `..` before matching `includeIf "gitdir:..."`.
- `GitConfigTest.php` now covers an absolute condition matching a provided
  non-existing `missing/../worktree/./.git` gitdir path, while a sibling
  condition for the unnormalized missing path remains rejected.
- The WordPress config include fixture/example now exposes the same planned
  Git-backed deployment policy boundary.

## Verification

- Red-first probe before the fix returned only `['base']` for a
  `missing/../worktree/.git` gitdir path against an absolute normalized
  `gitdir:` include condition.
- `php -l lanes/gitoxide/src/GitConfig.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 298 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exited `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 9470
  assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`: no whitespace errors.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
GitConfig parser, include resolver, and byte-oriented wildmatch implementation.

## Non-Overlap

This is additive to accepted config include work for directive case,
dot-slash missing paths, path interpolation, named-user interpolation, max
depth, legacy remote dot subsections, onbranch simple globs, POSIX classes,
bracket/double-star/trailing-backslash behavior, symlink gitdirs, and absolute
path sentinels. It is limited to the `gix_path::realpath()` fallback boundary
used by conditional `gitdir:` matching.
