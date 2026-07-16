# LightningCSS Transform Target Prefix Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T165302Z`

Base accepted HEAD from launcher: `c146184e42fb1e6c6f9e37fb2cce04912f64fe15`

Lane accepted source context: `35913132375e4ff72782ebae6dcc21290d6019bc`

## Source Truth

- Upstream source: `parcel-bundler/lightningcss` at pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_transform` has two `prefix_test` transform cases for `scale(0.5)` and `var(--transform)` under Firefox 6 and Safari 6 targets.
- `src/prefixes.rs` maps transform-family feature ranges:
  - `Transform` and `TransformOrigin`: WebKit for Android 2.1-4.4.3, Chrome 4-35, iOS Safari 3.2-8.1, Opera 15-22, Safari 3.1-8; Moz for Firefox 3.5-15; Ms for IE <= 9; O for Opera 10.5-12.
  - `Perspective`, `PerspectiveOrigin`, and `TransformStyle`: WebKit for Android 3-4.4.3, Chrome 12-35, iOS Safari 3.2-8.1, Opera 15-22, Safari 4-8; Moz for Firefox 10-15.
  - `BackfaceVisibility`: WebKit for Android 3-4.4.3, Chrome 12-35, iOS Safari 3.2-15.2, Opera 15-22, Safari 4-15.2; Moz for Firefox 10-15.

## Implemented

- `TransitionPrefixer` now adds and removes target-dependent vendor declarations for:
  - `transform` and `transform-origin`: `-webkit-`, `-moz-`, `-ms-`, and `-o-`.
  - `perspective`, `perspective-origin`, and `transform-style`: `-webkit-` and `-moz-`.
  - `backface-visibility`: `-webkit-` and `-moz-`.
- `TransitionPrefixerTest.php` adds focused upstream-backed assertions for helper parity and browser cutoff boundaries: Chrome 35/36, Firefox 15/16, IE 9/10, Opera 12/13, and Safari 15.2/15.3.
- `wordpress-target-prefix-boundaries.php` extends the existing target-boundary smoke with a block flip-card transform family and a Chrome 35 target output.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 395 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2343 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php --self-test` exits `0`.
- `php -l` on changed PHP files exits `0`.
- `git diff --check -- lanes/lightningcss` exits `0`.

Expected dashboard movement:

- PHP lane assertions: `2321 -> 2343`, `0` failures.
- Conservative mapped coverage: `1450 -> 1455 / 3532`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `TransitionPrefixer` target routing and declaration-group prefix rewrite helper.

## Non-overlap

The stale main-repo rework note for `CustomMediaTransformer.php` was from a May 25 custom-media slice and is unrelated to this target-prefix boundary work. This patch does not repeat accepted mask/mask-border, background-clip, display/flex, logical inset, text/text-emphasis/text-decoration, image-set, print/UI, or clip-path target-prefix coverage.
