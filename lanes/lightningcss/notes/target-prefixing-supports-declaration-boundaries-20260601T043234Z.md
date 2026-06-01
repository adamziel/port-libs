# Target Prefixing Supports Declaration Boundaries - 2026-06-01T04:32Z

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T043234Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream files inspected: `src/prefixes.rs` target ranges and `src/properties/prefix_handler.rs` declaration prefix emission.
- Direct native addon oracle at the pinned commit confirmed that declaration conditions in `@supports` are expanded or pruned alongside emitted target-prefixed declarations for appearance, user-select, print-color-adjust, clip-path, text-size-adjust, hyphens, backface-visibility, and text-decoration cases.

Behavior added:

- `TransitionPrefixer` now rewrites `@supports` declaration guards through the same target-prefix boundary decisions used for declaration output.
- Unprefixed declaration conditions gain needed prefixed alternatives, for example `appearance`, `user-select`, `print-color-adjust`, and `clip-path`.
- Stale prefixed alternatives are dropped when the selected browser target no longer needs that prefix and the equivalent unprefixed condition is present.
- Existing logical condition composition is preserved, including `and` groups and existing `or` alternatives.
- The previous backdrop-filter support-condition behavior is retained through the generic declaration-prefix path.

Evidence:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-supports-target-prefix-boundaries.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-supports-target-prefix-boundaries.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 986 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5994 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-supports-target-prefix-boundaries.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - Passed with no output.

Dependency closure:

- No new support component is needed. This reuses the existing target option flags, declaration parser, and `@supports` condition parser inside the native PHP target-prefixing pipeline.

Non-overlap:

- This does not repeat accepted direct declaration prefix boundary slices. Those emit declaration prefixes; this slice keeps the corresponding `@supports` declaration guards in parity with upstream.
- This does not edit the stale May 25 custom-media/import-tail current-rebase note surface.
- Conservative mapped coverage remains `2336 / 3532`; this strengthens the represented target-prefixing cluster rather than claiming a new denominator row.

Follow-up:

- Broader `@supports` prefix parity can still be expanded for additional target-prefix families such as filter/background-clip/animation/flex if a future upstream-backed slice shows missing support-condition behavior.
