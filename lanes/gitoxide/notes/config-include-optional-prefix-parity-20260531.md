# Gitoxide Config Include Optional Prefix Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T181837Z`
Base accepted HEAD: `f239ae84229f0ac8ecc07e38ef32523b43f8024f`

## Source Truth

- Upstream `gix-config-value/src/path.rs` strips the case-sensitive
  `:(optional)` marker in `Path::from()` before interpolation.
- Upstream `gix-config/src/file/includes/mod.rs` routes both include `path`
  values and `gitdir:` includeIf condition bodies through that `Path`
  interpolation path.
- `gix-config-value/tests/value/path.rs` covers the optional marker,
  case-sensitive prefix behavior, and optional-marker composition with
  `~/` and `%(prefix)/` interpolation.

## Native Change

- `GitConfig::resolvePath()` now strips `:(optional)` before resolving include
  paths.
- `GitConfig::gitDirMatches()` now strips `:(optional)` before interpolating
  and matching `gitdir:` / `gitdir/i:` condition paths.
- Marker matching remains case-sensitive, so `:(OPTIONAL)` is treated as a
  literal path prefix and does not accidentally include config files.
- The WordPress config include fixture now proves an optional-prefixed
  deployment policy include without shelling out to `git config`.

## Red-First Evidence

Before the implementation, this focused probe returned `'base'` instead of the
included value:

`[includeIf "gitdir:repo/"] path = :(optional)../policy.config`

After the fix, `GitConfigTest.php` includes the same upstream-shaped behavior
with an optional-prefixed `gitdir:` condition, an optional-prefixed include
path, `%(prefix)` composition, and a case-sensitive non-match guard.

## Verification

- `php -l lanes/gitoxide/src/GitConfig.php` passed.
- `php -l lanes/gitoxide/tests/GitConfigTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-config-include-conditional.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-config-include-conditional.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php` passed:
  `1 test files, 125 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed:
  `39 test files, 5032 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  exited 0.
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This reuses the existing native config
parser, include resolver, path interpolation, and bounded byte-glob matcher.

## Non-Overlap

This extends accepted config include/includeIf escape, double-star,
bracket/slash, POSIX class, malformed bracket, byte-safe wildmatch,
hasconfig-backslash, and path-interpolation slices. It does not touch protocol,
transport, pack, object database, reference, sparse checkout, pathspec, merge,
or credential behavior.
