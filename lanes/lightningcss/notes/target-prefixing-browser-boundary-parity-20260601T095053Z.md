# Shape Property WebKit Browser-Boundary Parity

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/prefixes.rs` `Feature::ShapeMargin | Feature::ShapeOutside | Feature::ShapeImageThreshold`.

Behavior ported:
- `shape-outside`, `shape-margin`, and `shape-image-threshold` now emit `-webkit-` declarations for Safari 7-10 and iOS Safari 8-10.
- Safari 6, iOS Safari 7, Safari 11, and iOS Safari 11 keep or prune to the unprefixed declarations.
- `@supports (shape-outside: ...)` conditions now gain the WebKit alternative only inside the same upstream target window, and stale WebKit alternatives are removed for modern targets.

Red-first evidence:
- Before the source change, `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` failed in `transition prefixer maps upstream shape property WebKit browser boundaries`: Safari 7 emitted `.foo{shape-outside:circle(50%);shape-margin:12px;shape-image-threshold:.5}` instead of adding the three WebKit declarations.

Focused evidence:
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with 1 test file, 1192 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-shape-prefixer.php --self-test` passed.
- PHP lint passed for `TransitionPrefixer.php`, `TransitionPrefixerTest.php`, and `wordpress-shape-prefixer.php`.
- `git diff --check -- lanes/lightningcss` passed.

WordPress smoke:
- `wordpress-shape-prefixer.php` models an aligned image block using `shape-outside`, `shape-margin`, `shape-image-threshold`, and a matching `@supports` guard so legacy Safari/iOS targets get WebKit-compatible text-wrap CSS without Node/WASM.

Dependency closure: no new support component is needed; this reuses the native PHP `TransitionPrefixer`, target-version routing, supports-declaration prefix rewriting, minifier output, and lane-local example coverage.

Non-overlap: avoids accepted viewport, mask, clip-path, CSS Regions, unicode-bidi, selector stale-prefix, SVG paint, media-query, CSSOM, SourceMap, CSS Modules, custom-at-rule, and bundle/import graph slices. This slice is limited to shape property target-prefix browser-boundary parity from upstream `src/prefixes.rs`.

Next task: remaining target-prefix gaps include `image-rendering` / `pixelated`, `overscroll-behavior`, `text-spacing`, and any grid `-ms-` boundary parity not already covered by property-value minification slices.
