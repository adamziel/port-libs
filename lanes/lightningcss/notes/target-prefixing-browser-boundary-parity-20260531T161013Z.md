# LightningCSS Target Prefixing Mask Browser Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T161013Z`

Accepted base: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`

Upstream source truth:
- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | nl -ba | sed -n '1436,1483p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | nl -ba | sed -n '27710,28234p'`
- Upstream `Feature::Mask*` WebKit ranges end at Chrome/Edge 119, Safari/iOS 15, Opera 105, Samsung 24, and Android 4.4.3.

Native PHP delta:
- `TransitionPrefixer` now computes `maskNeedsWebkit` from upstream browser boundaries.
- Mask declarations, mask-border declarations, mask-mode/mask-composite, and mask transition-property expansion only emit WebKit aliases when the target requires them.
- Modern targets drop stale paired `-webkit-mask-*` declarations when matching unprefixed mask declarations exist.
- Added `wordpress-mask-target-boundaries.php` to show Chrome 119 vs Chrome 120 cover-mask output without Node.

Evidence:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-mask-target-boundaries.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 344 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2103 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-mask-target-boundaries.php` -> exits 0.
- `git diff --check -- lanes/lightningcss` -> passed.

Non-overlap:
- Does not repeat accepted display/flex, logical inset, background-clip, legacy text/sticky, text-decoration, text-emphasis, image-set, keyframes, light-dark, media range, or mask prefix helper-composition slices. This slice only gates the already implemented mask WebKit prefixing against upstream browser boundary tables.

Dependency closure:
- No new support component is needed. This reuses the native `TransitionPrefixer` target-version encoder, declaration scanner, mask prefix/fallback machinery, and CSS minifier.
