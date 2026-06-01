# Gitoxide Config Include Directive Case Parity

Micro-slice: `gitoxide-config-include-conditional-parity-20260601T094354Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/includes/mod.rs`
  dispatches include resolution only for section header names whose raw spelling
  is exactly `include` or `includeIf`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/parse/section/mod.rs`
  keeps section lookup case-insensitive in general, but the include resolver
  calls `header.name.as_ref()` before comparing directive names.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config/src/file/init/from_env.rs`
  builds environment-pair sections through the same section-name surface, so
  exact directive spelling matters there as well once includes are resolved.

## Native Change

- `GitConfig` now retains each section's raw header name internally while still
  exposing and querying normalized lowercase section names through the public
  API.
- Include resolution now follows Gitoxide's exact directive spelling boundary:
  `[include]` and `[includeIf "..."]` resolve, while `[Include]`,
  `[includeif "..."]`, and `[IncludeIf "..."]` remain ordinary config sections.
- Environment-style config pairs preserve the first raw section spelling for
  include resolution, matching Gitoxide's section creation path.
- The WordPress config include fixture/example now proves that a wrong-cased
  deployment `includeif` policy does not load.

## Evidence

- Red-first probe before the implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` failed
  `include directive section names stay exact case like gix-config resolution`
  because `[Include]` loaded `mixed-include.config`.
- `php -l lanes/gitoxide/src/GitConfig.php`,
  `php -l lanes/gitoxide/tests/GitConfigTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php`,
  and `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  all reported no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed:
  `1 test files, 269 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed:
  `40 test files, 8535 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited `0`.

## Dependency Closure

No new support component is needed. This reuses the native Git config parser,
include resolver, environment-pair parser, path interpolation, and byte-aware
wildmatch implementation; no shared dependency row or activation gate is
proposed.

## Non-Overlap

This extends accepted config include/includeIf path interpolation, named-user,
dot-slash, symlink gitdir, optional-prefix, POSIX class, malformed-bracket,
trailing-backslash, reversed-range, byte-wildmatch, and environment-pair
slices with the remaining exact directive-name dispatch boundary. It does not
touch protocol, transport, pack/index, reference transactions, object database,
sparse checkout, attributes/pathspec, tree merge, merge-base, credentials, or
URL/refspec behavior.
