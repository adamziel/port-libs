# Gitoxide Config Include Gitdir Backslash Byte Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T210003Z`

## Source Truth

- `gix-config/src/file/includes/mod.rs` normalizes gitdir separators through
  `gix_path::to_unix_separators_on_windows()`, so Unix paths keep `\` as an
  ordinary path byte.
- The same `gitdir_matches()` path passes patterns to
  `gix_glob::wildmatch(..., Mode::NO_MATCH_SLASH_LITERAL)`, where `/` is the
  only component separator. A `?` pattern can match a Unix backslash byte, but
  a literal `/` pattern cannot.
- A local Git oracle agrees for this boundary: `gitdir:work/slash/` keeps the
  base value for a `work\slash/.git` repository, while `gitdir:work?slash/`
  loads the conditional include.

## Native Changes

- `GitConfig::normalizePath()` is now OS-aware. It keeps the existing Windows
  backslash-to-slash conversion, but preserves backslash bytes on Unix before
  `gitdir` matching and relative include path resolution.
- `GitConfigTest.php` adds focused Unix gitdir conditions for a worktree whose
  path contains a literal backslash byte.
- The WordPress config include fixture/example now records a legacy deployment
  checkout where the slash policy is rejected and the wildcard policy is
  accepted without shelling out to `git config`.

## Verification

- Red-first before the implementation:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - Failed at `1 test files, 119 assertions, 2 failures`:
    `gitdir:work/slash/` loaded `should-not-load`, and the WordPress
    wildcard policy was still `NULL`.
- After the implementation:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - `1 test files, 141 assertions, 0 failures`
  - `TMPDIR=/home/claude/port-libs/.tmux-team/tmp/gitoxide-config-include-test-tmp-* php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5697 assertions, 0 failures`

The first full-lane attempt with the shared `/tmp` failed because unrelated
temp-backed tests hit `errno=122 Disk quota exceeded`. Re-running with `TMPDIR`
on the main filesystem passed and the temporary directory/log were removed.

## Dependency Closure

No new support component is needed. This reuses the native config parser,
include resolver, filesystem path handling, and bounded byte-oriented
wildmatch matcher.

## Non-Overlap

This extends accepted config include escape, double-star, bracket slash, POSIX
class, malformed bracket, byte-safe malformed-UTF-8, hasconfig backslash,
path-interpolation, and optional-prefix slices. It does not touch protocol,
transport, object database, pack, reference, sparse checkout, pathspec, URL,
or merge behavior. The old Gitoxide smart-HTTP rework notes are stale for this
slice because they target receive-pack redirect/status metadata conflicts, not
config include parity.
