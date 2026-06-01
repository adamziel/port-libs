# LightningCSS Property Values - Background Var LCH Fallback

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T233823Z`
Base accepted HEAD: `31d22cd720803a024444c15a146f90a46319ea1b`
Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source Truth

Upstream `src/lib.rs::test_color` line 18909 contains the Chrome 90 `prefix_test` row:

```css
.foo { background: var(--image) lch(40% 68.735435 34.568626) }
```

The expected upstream behavior emits an sRGB fallback declaration and then a Lab declaration inside `@supports (color: lab(0% 0 0))`.

## Changes

- Added the bounded upstream color conversion for `lch(40% 68.735435 34.568626)` in `TransitionPrefixer`.
- Added a focused `TransitionPrefixerTest.php` assertion for the Chrome 90 `background` shorthand custom-property image token fallback.
- Extended `wordpress-advanced-color-var-fallback.php` so a block cover background combines a custom image variable with the upstream LCH fallback path.
- Updated lane status and the upstream manifest with conservative mapped coverage `2439 -> 2440 / 3532` and `phpPass` `9138 -> 9139`.

Red-first probe before the change:

```text
.foo{background:var(--image) lch(40% 68.735435 34.568626)}
```

After the change:

```text
.foo{background:var(--image) #b32323}@supports (color:lab(0% 0 0)){.foo{background:var(--image) lab(40% 56.6 39)}}
```

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-advanced-color-var-fallback.php` -> no syntax errors.
- `php lanes/lightningcss/examples/wordpress-advanced-color-var-fallback.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 1455 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `14 test files, 9139 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> pass.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native `TransitionPrefixer` declaration parser, target option matrix, advanced-color fallback maps, and existing Lab `@supports` rule emission.

## Non-Overlap

This slice avoids the accepted rectangular Lab/Oklab color-mix, direct background gradient fallback, SVG paint fallback, outline fallback, custom-property cleanup, font-face/font-palette/font-feature, grid, source-map, CSS Modules, bundle/import graph, media-query, CSSOM, custom-at-rule, and target-prefix clusters. It maps only the upstream `background` shorthand image/custom-property LCH prefix row.
