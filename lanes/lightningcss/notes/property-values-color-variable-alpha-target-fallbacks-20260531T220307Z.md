# LightningCSS Property Values: Color Variable Alpha Target Fallbacks

Slice: `lightningcss-property-values-color-font-grid-parity-20260531T220307Z`

Base: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`

## Upstream Source Truth

Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Pristine source was read with:

```sh
git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs
```

Relevant upstream rows are in `src/lib.rs::test_color`:

- Lines 19075-19133: custom-property `rgb()` values with `var(--alpha)` and Safari 11/13 target outputs.
- Lines 19145-19201: custom-property `hsl()` values with `var(--alpha)` / `calc(var(--alpha) / 2)` and Safari 11/13 target outputs.

This handoff conservatively maps 4 upstream helper rows:

- Safari 11 `rgb()` custom properties with variable alpha to legacy `rgba()`.
- Safari 13 `rgb()` custom properties to canonical modern `rgb()` where fixed or `yellow` relative components can be resolved.
- Safari 11 `hsl()` custom properties with variable alpha to legacy `hsla()`.
- Safari 13 `hsl()` custom properties to canonical modern `hsl()` for fixed components.

## Native Delta

`TransitionPrefixer` now has target-aware custom-property modern color rewriting for Safari alpha fallback behavior:

- Safari 11 / iOS Safari 11 and older emit legacy `rgba()` / `hsla()` for supported fixed-channel RGB/HSL custom-property values with `var()` or `calc()` alpha.
- Safari 12-13 / iOS Safari 12-13 canonicalize supported fixed-channel RGB values and fixed HSL values without switching to legacy syntax.
- `none` channels and channel components containing `var()` / `calc()` remain untouched, matching the upstream guard cases in the same helper block.
- The bounded relative-color support resolves the upstream `rgb(from yellow r g b / var(--alpha))` and Safari 11 `hsl(from yellow h s l / var(--alpha))` rows without claiming arbitrary relative-color conversion.

## Evidence

Red-first before implementation:

```sh
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
# 1 test files, 670 assertions, 2 failures
```

Final focused verification:

```sh
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
# 1 test files, 672 assertions, 0 failures
```

Full lane verification:

```sh
php tools/run-tests.php lanes/lightningcss/tests
# 13 test files, 4518 assertions, 0 failures
```

Example smoke:

```sh
php lanes/lightningcss/examples/wordpress-alpha-color-fallback.php --self-test
# OK
```

PHP lint:

```sh
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-alpha-color-fallback.php
# no syntax errors
```

Coverage movement:

- `phpPass`: `4514 -> 4518`
- mapped upstream coverage: `2152 / 3532 -> 2156 / 3532`

## Non-Overlap

This does not repeat the accepted alpha-hex target fallback cluster, advanced color fallback layers, color-mix/minifier parity, font-palette values, or grid shorthand/value work. It is limited to upstream `src/lib.rs::test_color` custom-property RGB/HSL variable-alpha Safari target output.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP parser/prefixer/minifier path and the existing WordPress alpha-color example smoke. Full upstream Rust/Node/WASM runners were not executed for this isolated handoff.
