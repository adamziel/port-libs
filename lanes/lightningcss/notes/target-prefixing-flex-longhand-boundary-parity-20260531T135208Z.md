# LightningCSS Flex Longhand Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T135208Z`

Accepted base: `f45c4dff3200fbbe1797b337ba6f15c6b2197784`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '4900,6205p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/properties/flex.rs | sed -n '200,340p;680,790p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/properties/align.rs | sed -n '980,1185p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '695,744p;2240,2275p'`
- Mapped 33 focused upstream-aligned checks from `src/lib.rs::test_flex` and browser-boundary logic in `src/prefixes.rs`.

Native PHP delta:

- `TransitionPrefixer` now emits target-dependent 2009/2012 flex prefixes for `flex-direction`, `flex-wrap`, `flex-flow`, `flex-grow`, `flex-shrink`, `flex-basis`, `flex`, `order`, `align-content`, `justify-content`, `align-self`, and `align-items`.
- Added legacy box-alignment expansion for `place-content`, `place-self`, and `place-items` when old flex targets need longhand fallbacks.
- Modern targets remove stale matching prefixed flex declarations while preserving mismatched fallback declarations such as a distinct `-ms-flex` value.
- Added `wordpress-flex-longhand-prefixer.php` for block flex-flow, navigation alignment, order, and button alignment CSS without Node.

Red-first evidence:

- After adding the focused assertions and before implementation:
  - `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 254 assertions, 4 failures`

Verification:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-flex-longhand-prefixer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 283 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1652 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-flex-longhand-prefixer.php --self-test` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> no whitespace errors.

Non-overlap:

- Does not repeat accepted display flex value aliasing, legacy text/sticky prefixes, UI `user-select`/`appearance` boundaries, print-color-adjust, image-set, box-shadow, text-emphasis, text-decoration, keyframes, `light-dark`, media range/resolution fallback, CSSOM shorthand, grid value, source-map, or bundle/CSS Modules graph slices.
- Leaves logical-position/inset target prefixes and the two remaining display-flex cascade-order edge cases for later target-prefixing work.

Dependency closure:

- No new support component is needed. This reuses the native `TransitionPrefixer` target-version encoder, declaration scanner, existing minifier normalization, and vendor-prefix rewriting machinery.
