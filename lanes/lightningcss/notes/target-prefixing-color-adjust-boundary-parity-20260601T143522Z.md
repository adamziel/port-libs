# Target Prefixing Color Adjust Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T143522Z`

Source truth:
- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs` maps `Feature::PrintColorAdjust | Feature::ColorAdjust` through the same target-prefix table: WebKit for Android 4.4-4.4.3, Chrome 17-135, Edge 79-135, iOS Safari 6-15.2, Opera 15+, Safari 6-15.2, and Samsung 4-28; Mozilla for Firefox 48-96.

Behavior:
- `TransitionPrefixer::rewritePrintColorAdjustPrefixEntries()` now applies the existing print-color-adjust WebKit/Mozilla boundary matrix to the deprecated `color-adjust` alias.
- Style declarations insert required `-webkit-color-adjust` and `-moz-color-adjust` fallbacks at the same browser boundaries as upstream `Feature::ColorAdjust`.
- Stale `-webkit-color-adjust` and `-moz-color-adjust` declarations are pruned when the selected targets do not require them and an unprefixed `color-adjust` declaration remains.
- `@supports (color-adjust: exact)` conditions expand and stale prefixed alternatives collapse with the same declaration-prefix helper used by print-color-adjust.

Red-first evidence:
- Before the implementation, this probe did not add the upstream alias prefixes:
  - `php -r 'require "tools/bootstrap.php"; $p = new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".foo { color-adjust: exact; }", ["chrome" => 135, "firefox" => 96]), PHP_EOL; echo $p->prefixForTargets("@supports (color-adjust: exact) { .foo { color-adjust: exact; } }", ["chrome" => 135, "firefox" => 96]), PHP_EOL;'`
  - Output: `.foo{color-adjust:exact}` and `@supports (color-adjust:exact){.foo{color-adjust:exact}}`

Verification:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` => no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` => no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-color-adjust-prefixer.php` => no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 1355 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-color-adjust-prefixer.php --self-test` => exit 0
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 8247 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` => exit 0

Status delta:
- Full lane assertion count moves from `8231` to `8247`.
- Conservative mapped coverage remains `2393 / 3532` because this deepens the already represented print-color-adjust target-prefix family with upstream's paired `Feature::ColorAdjust` alias rather than adding a new manifest denominator row.

Non-overlap:
- This does not repeat accepted print-color-adjust stale cleanup, cross-fade, image-set, mask, selector, placeholder, supports-break, media-query, CSSOM, CSS Modules, source-map, custom at-rule, or bundle/import graph slices.

Dependency closure:
- No new support component is needed. This reuses `TransitionPrefixer`, the existing target version routing, the generic vendor-prefixed declaration group helper, and a lane-local WordPress example smoke.
