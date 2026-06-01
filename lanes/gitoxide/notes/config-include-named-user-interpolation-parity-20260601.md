# Gitoxide Config Include Named User Interpolation Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T081830Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config-value/src/path.rs`
  at pinned upstream `87433ed33eee9ba974111d20b854f6acb07cd4a6` expands
  `~user/...` paths through `Path::interpolate()` when a caller supplies a
  `home_for_user` resolver.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  routes both `include.path` values and `includeIf "gitdir:..."` condition
  bodies through the same interpolation flow before resolving include files or
  matching the active Git directory.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config-value/tests/value/path.rs`
  includes the focused `tilde_with_given_user` behavior for `~user/` path
  expansion.

## Native Change

- `GitConfig` now accepts a caller-supplied `userHomeDirs` map and uses it for
  named-user interpolation without reading the system user database.
- Named-user interpolation now works for direct `include.path`,
  optional-prefixed include paths, `includeIf "gitdir:~user/..."` conditions,
  and environment-style config pairs.
- Missing user mappings preserve the existing bounded behavior: non-fatal
  interpolation skips the include, while `errOnInterpolationFailure` raises an
  error.
- The WordPress config include fixture/example now covers deployment policies
  loaded through named-user path and gitdir interpolation maps.

## Verification

- `php -l lanes/gitoxide/src/GitConfig.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 258 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 8195
  assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exited 0.
- `git diff --check -- lanes/gitoxide`: passed.

## Dependency Closure

No new support component is needed. This slice reuses the native config parser,
include resolver, and interpolation path and adds only a caller-supplied
`userHomeDirs` map. It does not read live process environments, passwd/user
databases, credential stores, provider config, OAuth/browser state, or network
remotes.

## Non-Overlap

This extends the accepted config include path interpolation, optional-prefix,
dot-slash, symlink gitdir, drive-prefix, POSIX class, environment-pair, and
wildmatch slices. It does not touch protocol, receive-pack, send-pack,
reference transaction, pack/object database, sparse-checkout, tree/pathspec,
credential-helper, or transport behavior.
