# Linear Gradient Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T232802Z`

Base accepted HEAD: `afee0853cdadd52fa12dbc1e24d633ac7329910c`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs` `Feature::LinearGradient | RepeatingLinearGradient | RadialGradient | RepeatingRadialGradient` browser ranges:
  - WebKit: Android 2.1-4.4.3, Chrome 4-25, iOS Safari 3.2-6, Safari 4-6.
  - Mozilla: Firefox 3.6-15.
  - Opera: Opera 11-12.
- `src/lib.rs::test_gradients` `prefix_test` cases confirm old Chrome 8 emits `-webkit-gradient(...)`, `-webkit-linear-gradient(...)`, and the unprefixed value; Chrome 10 emits WebKit linear only plus unprefixed; modern targets remove stale prefixed gradients when the unprefixed gradient is present.

## Implementation

- `TransitionPrefixer` now rewrites bounded `background-image: linear-gradient(...)` declarations for simple two-stop gradients:
  - emits old `-webkit-gradient(linear,...)` for the old WebKit boundary;
  - emits `-webkit-linear-gradient(...)`, `-moz-linear-gradient(...)`, and `-o-linear-gradient(...)` based on upstream target ranges;
  - converts `to right` to old prefixed direction/point syntax;
  - removes stale prefixed linear-gradient declarations for modern targets only when a matching unprefixed declaration remains;
  - preserves prefixed-only declarations and skips advanced/custom color gradient stacks to avoid overlapping the accepted advanced-color fallback cluster.
- Added `examples/wordpress-gradient-prefixer.php` to self-check WordPress block-cover gradient output for Chrome 8, Chrome 10, and modern stale-prefix cleanup without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 737 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4831 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-gradient-prefixer.php`
  - exited `0` and printed the expected Chrome 8, Chrome 10, and modern outputs.

## Mapping And Non-Overlap

- Conservative mapped coverage moves from `2198 / 3532` to `2202 / 3532`.
- Counted checks: Chrome 8 old WebKit default gradient, Chrome 8 `to right` conversion, Chrome 10 WebKit-only boundary, and modern stale-prefixed cleanup. Firefox/Opera range boundaries are verified in the focused PHP test against `src/prefixes.rs` but kept inside the same conservative cluster count.
- This does not repeat accepted linear-gradient value minification, radial/conic gradient minification, list-style advanced-color gradient fallback, image-set prefixing, background-size/background-origin prefixing, text-decoration longhand prefixing, selector prefixing, source-map, CSS Modules, or custom at-rule clusters.

## Dependency Closure

- No new support component is needed. The slice reuses `TransitionPrefixer` target-version routing, declaration parsing, existing value minification, top-level function/list splitting, and lane-local PHP test/example harnesses.

## Follow-Up

- Extend the same upstream prefix ranges to non-overlapping repeating/radial gradient target-prefix cases or to `background` shorthand value-prefixing after adding focused tests for those forms.
