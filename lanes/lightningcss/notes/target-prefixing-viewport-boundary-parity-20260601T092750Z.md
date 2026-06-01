# LightningCSS Target Prefixing Viewport Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T092750Z`

Source truth:
- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs` `Feature::AtViewport` maps `Edge 12..18` and `IE >= 10` to `VendorPrefix::Ms`, and `Opera 11..12.1` to `VendorPrefix::O`.
- `src/rules/viewport.rs` serializes the viewport rule's `vendor_prefix` before `viewport`.

Pre-change probe:
- `php -r 'require "tools/bootstrap.php"; $class = "PortLibs\\\\LightningCSS\\\\TransitionPrefixer"; $p = new $class(); echo $p->prefixForTargets("@viewport { width: device-width; zoom: 1; }", ["ie" => 10]), "\n";'`
- Before this slice the PHP port returned only `@viewport{width:device-width;zoom:1}` for IE 10.

Implementation:
- `TransitionPrefixer` now recognizes top-level `@viewport`, `@-ms-viewport`, and `@-o-viewport` at-rules.
- Canonical `@viewport` emits `@-ms-viewport` for IE 10+ and Edge 12-18, `@-o-viewport` for Opera 11-12.1, then the unprefixed rule.
- Existing prefixed viewport rules are deduped when paired with a canonical rule and pruned when the requested targets no longer need that vendor prefix.
- Added a WordPress smoke covering responsive block CSS using viewport rules for IE 10, Edge 18/19, and Opera 12.1/13 target boundaries.

Verification:
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 1175 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7154 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-viewport-prefix-boundaries.php --self-test` -> passed and printed the expected target-boundary outputs.
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-viewport-prefix-boundaries.php` -> no syntax errors.
- `git diff --check -- lanes/lightningcss` -> passed.

Dependency closure:
- No new support component is needed. The slice reuses the existing CSS minifier, target-version encoding helpers, and target-prefix rewrite pass.

Non-overlap:
- This is a bounded AtViewport at-rule prefixing cluster. It does not touch the accepted custom at-rule location metadata, CSS Regions declaration prefixing, unicode-bidi value prefixing, stale selector prefix pruning, source-map, CSS Modules, or bundle/import graph surfaces.
- Root harness was not run; this isolated micro-slice used lane-focused verification only.
