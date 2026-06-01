# LightningCSS Target Prefixing: print-color-adjust Safari/Samsung Boundaries

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` defines `print-color-adjust` as a `PrintColorAdjust(..., VendorPrefix) / WebKit` property.
- `src/prefixes.rs` maps `Feature::PrintColorAdjust | Feature::ColorAdjust` to WebKit prefixes for Safari/iOS Safari `6.0..15.2` and Samsung Internet `4..28`.
- The native upstream binding confirms:
  - Safari `15.2` and Samsung `28`: `.foo{-webkit-print-color-adjust:exact;print-color-adjust:exact}`
  - Safari `15.3` and Samsung `29`: `.foo{print-color-adjust:exact}`

## Change

- Extended `TransitionPrefixer`'s existing `printColorAdjustNeedsWebkit` browser table from Safari/iOS Safari `15.0` to `15.2`.
- Added the missing Samsung Internet `4..28` WebKit boundary.
- Added four focused assertions for Safari `15.2`/`15.3` and Samsung `28`/`29`.
- Updated `examples/wordpress-print-color-adjust-prefixer.php` so WordPress print/PDF export CSS now smokes the Safari and Samsung exact-color boundaries without Node/WASM.

## Verification

```bash
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-print-color-adjust-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php lanes/lightningcss/examples/wordpress-print-color-adjust-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests
git diff --check -- lanes/lightningcss
```

Results:

- `TransitionPrefixer.php`, `TransitionPrefixerTest.php`, and the WordPress example all passed syntax checks.
- `TransitionPrefixerTest.php`: `1 test files, 915 assertions, 0 failures`.
- Full LightningCSS lane: `13 test files, 5638 assertions, 0 failures`.
- WordPress print-color example exited `0` and printed the expected Chrome, Firefox, Safari, Samsung, and combined target outputs.
- `git diff --check -- lanes/lightningcss`: passed with no output.

## Non-Overlap

This slice only deepens the accepted `print-color-adjust` target-prefix cluster. It does not repeat cursor, selector, placeholder, animation timeline, font typography, length fallback, mask, filter/backdrop-filter, clip-path, display/flex, logical-size, CSSOM, source-map, bundler/import graph, CSS Modules, or custom at-rule work. The stale May 25 custom-media rework note is unrelated to this target-prefixing surface.

## Dependency Closure

No new support component is needed. The existing native PHP `TransitionPrefixer` target table is sufficient; upstream Node/Rust/WASM runners remain out of scope for this isolated micro-slice.
