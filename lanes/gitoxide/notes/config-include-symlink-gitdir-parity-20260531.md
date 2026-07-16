# Gitoxide Config Include Symlink Gitdir Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T220824Z`

Base accepted HEAD: `6f5231cf32a6827b588751d49dba711af77e658b`

## Source Truth

- Upstream `gix-config/src/file/includes/mod.rs` checks `gitdir` includeIf patterns against the supplied gitdir and then against `realpath(git_dir)`.
- Upstream `gix-config/tests/config/file/init/from_paths/includes/conditional/gitdir/mod.rs` covers symlinked repository behavior for `gitdir:~/worktree/`, `gitdir:./symlink-worktree/.git`, `gitdir:symlink-worktree/`, and `gitdir/i:SYMLINK-WORKTREE/`.

## Native PHP Delta

- Added focused `GitConfigTest.php` coverage for symlinked gitdir conditions:
  - tilde-expanded pattern matches the real repository path through realpath fallback;
  - dot-relative user-config pattern matches the literal symlink path;
  - relative `gitdir:` pattern matches the literal symlink worktree;
  - `gitdir/i:` matches the symlink worktree case-insensitively.
- Extended the WordPress config include smoke fixture with a symlinked deployment checkout so the example exercises the same includeIf behavior without shelling out to `git`.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - `1 test files, 154 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5920 assertions, 0 failures`

## Coverage Delta

- Focused GitConfig assertions: `141 -> 154` (`+13`).
- Conservative mapped denominator: `1647 / 2886 -> 1648 / 2886`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP filesystem, symlink, path interpolation, and byte-aware wildmatch behavior in `GitConfig`.
