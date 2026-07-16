# Gitoxide Config Include Max Depth Conditional Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T110253Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  checks include recursion depth before resolving a config at that depth.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/tests/config/file/init/from_paths/includes/unconditional.rs`
  covers the upstream max-depth boundary: depth `0` with depth errors enabled
  raises `IncludeDepthExceeded`, while disabled errors stop following includes.
- Conditional includes use the same resolver path, so an `includeIf
  onbranch:` chain must observe the same zero-depth and truncation behavior.

## Implementation

- `GitConfig::resolveIncludes()` no longer treats `maxDepth = 0` as an
  unconditional silent no-follow. It now applies the same depth check at the
  current recursion depth and only returns silently when
  `errOnMaxDepthExceeded` is false.
- `GitConfigTest.php` adds a focused conditional include chain:
  `includeIf "onbranch:deploy/"` loads a child config, the child has a nested
  unconditional include, and max-depth settings verify upstream-compatible
  error and truncation boundaries.
- The WordPress config include fixture/example now exposes the deployment
  policy signal for full nested loading, first-level-only loading, nested
  suppression at `maxDepth = 1`, and the strict `maxDepth = 0` error.

## Verification

- Red-first probe before the fix: `GitConfig::fromFile(..., ['branchName' =>
  'refs/heads/deploy/site', 'maxDepth' => 0])` returned `no-error` for a
  matching `includeIf onbranch` include.
- `php -l lanes/gitoxide/src/GitConfig.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/GitConfigTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`: `1 test
  files, 282 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`:
  exits `0`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
config parser, include resolver, conditional matcher, focused lane test
harness, and WordPress-oriented example. No Git shell-out, live provider,
credential store, or upstream Cargo workspace runner was used.

## Non-overlap

This extends accepted config include work for directive case, dot-slash
missing paths, environment pairs, named-user interpolation, gitdir sentinels,
symlink gitdirs, path interpolation, optional prefixes, wildcard byte matching,
POSIX classes, malformed brackets, and trailing backslash aborts. It is limited
to include recursion depth handling shared by conditional include resolution.
