# Gitoxide Config Include Dot-Slash Missing-Path Parity

Slice: `gitoxide-config-include-conditional-parity-20260601T055811Z`

## Upstream Source Truth

- `gix-config/src/file/includes/mod.rs` resolves `gitdir:` condition paths
  starting with `./` against the directory of the config file that owns the
  `includeIf` section.
- `gix-config/tests/config/file/init/from_paths/includes/conditional/gitdir/mod.rs`
  covers `dot_slash_path_is_replaced_with_directory_containing_the_including_config_file`
  and the environment/pathless `dot_slash_from_environment_causes_error`
  boundary.
- `gix-config/src/file/includes/types.rs` exposes
  `err_on_missing_config_path`; when disabled, pathless configs suppress both
  relative include paths and `gitdir:./...` conditions that require an owning
  config path.

## Change

- `GitConfigTest.php` now locks down `gitdir:./` matching from a user config
  path, non-matching sibling dot-slash conditions, default missing-config-path
  errors for pathless config strings, and `errOnMissingConfigPath=false`
  suppression.
- The WordPress config include fixture/example now exposes a
  `dotSlashRootPolicy` loaded from a `$HOME/.gitconfig` style config plus a
  non-matching `dotSlashMissPolicy`.
- The native implementation already had the required path-context behavior;
  this slice adds upstream-shaped parity coverage and user-visible fixture
  evidence rather than changing parser code.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - `1 test files, 229 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 7685 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  - exited 0
- `php -l lanes/gitoxide/tests/GitConfigTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`,
  and `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  - no syntax errors
- `php -r 'foreach (["lanes/gitoxide/lane-status.json", "lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'`
  - both JSON files decoded successfully
- `git diff --check -- lanes/gitoxide`
  - exited 0

## Dependency Closure

No new support component is needed. This reuses the existing native Git config
parser, include resolver, path interpolation, and bounded wildmatch helpers; no
shared dependency row or activation gate is proposed.

## Non-Overlap

This extends accepted config include/includeIf work with the remaining
`gitdir:./` config-path and pathless-config suppression boundary. It does not
repeat drive-prefix, symlink realpath, optional-prefix, POSIX class,
wildmatch-byte, bracket-slash, malformed-bracket, trailing-backslash, or
legacy remote subsection slices, and it does not touch transport, protocol,
pack, reference transaction, object database, pathspec, sparse-checkout, or
merge behavior.
