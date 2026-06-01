# Gitoxide Config Include Gitdir Sentinel Parity - 2026-06-01

Slice: `gitoxide-config-include-conditional-parity-20260601T034323Z`

Base accepted HEAD: `6a9d70d6e954052f2443a5cdc428898114c4a14e`

## Upstream Source Truth

- Re-read pinned upstream `gix-config/src/file/includes/mod.rs` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read upstream
  `gix-config/tests/config/file/init/from_paths/includes/conditional/gitdir/mod.rs`.
- Mapped the remaining gitdir sentinel and absolute-boundary cases:
  `tilde_alone_does_not_match_even_if_home_is_git_directory`,
  `double_slash_does_not_match`,
  `absolute_git_dir_with_os_separators_match`,
  `absolute_worktree_dir_with_os_separators_does_not_match_if_trailing_slash_is_missing`,
  `absolute_worktree_dir_with_os_separators_matches_with_trailing_glob`,
  `dot_dot_slash_prefixes_are_not_special_and_are_not_what_you_want`, and
  `leading_dots_are_not_special`.

## Native PHP Delta

- Added focused `GitConfigTest.php` coverage for the upstream gitdir sentinel
  matrix:
  - `gitdir:~` remains a literal non-match even when the home directory is the
    worktree root.
  - `gitdir://...` and `gitdir:../` remain literal non-matches.
  - an absolute `.git` path matches exactly.
  - an absolute worktree path without a trailing glob does not match the
    `.git` directory.
  - an absolute worktree path with `/**` matches the `.git` directory.
  - leading-dot repository names match through ordinary relative gitdir
    conditions.
- Extended the WordPress config include fixture/example with the same
  deployment-policy signals for absolute worktree versus absolute gitdir
  matching and sentinel non-matches.

## Verification

- `php -l lanes/gitoxide/tests/GitConfigTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - `1 test files, 213 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 7204 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  - exited `0`

## Coverage Delta

- Focused `GitConfigTest.php`: `193 -> 213` assertions (`+20`).
- Conservative mapped denominator: `1721 / 2886 -> 1722 / 2886`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
config parser, path interpolation, realpath fallback, and byte-aware wildmatch
implementation.

## Non-Overlap

This verifies a bounded upstream `gitdir:` sentinel and absolute path cluster.
It does not repeat the accepted config include double-star, escaped backslash,
bracket slash, POSIX class, optional-prefix, symlink, trailing-backslash, or
legacy remote subsection slices, and it does not touch transport, protocol,
pack, object database, reference transaction, sparse-checkout, tree/pathspec,
or merge behavior.
