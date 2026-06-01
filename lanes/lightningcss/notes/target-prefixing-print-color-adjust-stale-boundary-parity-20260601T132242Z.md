# Target Prefixing Print Color Adjust Stale Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T132242Z`

Source truth:
- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` registers `print-color-adjust` as a `VendorPrefix` property with WebKit-prefixed parsing.
- `src/prefixes.rs` `Feature::PrintColorAdjust` maps WebKit prefixes for Android 4.4-4.4.3, Chrome 17-135, Edge 79-135, iOS Safari 6-15.2, Opera 15+, Safari 6-15.2, and Samsung 4-28, plus Mozilla prefixes for Firefox 48-96.

Behavior:
- `TransitionPrefixer::rewritePrintColorAdjustPrefixEntries()` now reuses the generic vendor declaration prefix group.
- Existing insertion behavior is preserved for WebKit and Mozilla target ranges.
- Stale duplicate `-webkit-print-color-adjust` and `-moz-print-color-adjust` declarations are now pruned when the selected target boundary does not require that prefix and an equivalent unprefixed declaration is present.
- The same cleanup applies to style rules inside rewritten `@supports` blocks.

Red-first evidence:
- Before the implementation, this probe preserved stale prefixes:
  - `php -r 'require "tools/bootstrap.php"; $p = new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".foo { -webkit-print-color-adjust: exact; -moz-print-color-adjust: exact; print-color-adjust: exact; }", ["chrome" => 136, "firefox" => 97]), PHP_EOL;'`
  - Output: `.foo{-webkit-print-color-adjust:exact;-moz-print-color-adjust:exact;print-color-adjust:exact}`

Verification:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` => no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` => no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-print-color-adjust-prefixer.php` => no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 1308 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 8024 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-print-color-adjust-prefixer.php --self-test` => exit 0
- `git diff --check -- lanes/lightningcss` => exit 0

Status delta:
- Full lane assertion count moves from `8018` to `8024`.
- Conservative mapped coverage remains `2392 / 3532` because this deepens the already represented upstream `print-color-adjust` target-prefix cluster rather than adding a new denominator row.

Non-overlap:
- This does not repeat accepted selector stale-prefix pruning, Safari text-decoration boundaries, supports declaration prefix insertion, image-set, backdrop-filter, UI, text-emphasis, mask, display/flex, media-query, CSSOM, CSS Modules, source-map, custom at-rule, or bundle/import graph slices.

Dependency closure:
- No new support component is needed. This reuses `TransitionPrefixer`, the existing target version routing, the generic vendor-prefixed declaration group helper, and the existing WordPress print-color-adjust example.
