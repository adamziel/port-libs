# Gitoxide Config Include Path Interpolation Parity

Slice: `gitoxide-config-include-conditional-parity-20260531T154253Z`

## Source Truth

- `gix-config-value/src/path.rs` only expands `~/` and leading
  `%(prefix)/`. A bare `~` remains a literal path, and `./%(prefix)/...`
  stays literal instead of substituting the install prefix.
- `gix-config/src/file/includes/mod.rs` applies the same interpolation path to
  conditional include paths and `gitdir:` condition patterns. Unsupported
  interpolation returns no include when interpolation failures are non-fatal,
  and raises an include error when configured as fatal.

## Native Changes

- `GitConfig::interpolatePath()` now follows the upstream sentinels:
  literal `~`, current-user `~/`, install-prefix `%(prefix)/`, and literal
  `./%(prefix)/`.
- Added an `installPrefix` option for bounded `%(prefix)`-style include path
  parity without shelling out or reading process-global install state.
- Unsupported named-user interpolation is skipped by default and throws when
  `errOnInterpolationFailure` is enabled.
- The WordPress config include fixture/example now proves literal `~`,
  install-prefix, and literal `./%(prefix)/` policy includes.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/GitConfigTest.php`
  - `1 test files, 118 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4818 assertions, 0 failures`
- `for f in lanes/gitoxide/src/GitConfig.php lanes/gitoxide/tests/GitConfigTest.php lanes/gitoxide/fixtures/wordpress-config-include-conditional.php lanes/gitoxide/examples/wordpress-config-include-conditional.php; do php -l "$f"; done`
  - no syntax errors
- `php lanes/gitoxide/examples/wordpress-config-include-conditional.php`
  - exited 0
- `php -r 'foreach (["lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json", "lanes/gitoxide/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'`
  - both JSON files decoded
- `git diff --check -- lanes/gitoxide`
  - exited 0

## Dependency Closure

No new support component is needed. This reuses the native Git config parser,
include resolver, and filesystem path handling; no shared dependency row or
activation gate is proposed.

## Non-Overlap

This is additive to the accepted config include escape, double-star,
bracket/slash, POSIX class, byte-safe wildmatch, hasconfig backslash, and
malformed-bracket slices. It does not touch protocol, transport, refs,
objects, packs, tree/pathspec walking, sparse checkout, or merge behavior.
