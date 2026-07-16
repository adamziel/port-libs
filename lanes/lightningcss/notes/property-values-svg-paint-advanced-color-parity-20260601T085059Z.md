# Property Values SVG Paint Advanced Color Parity - 2026-06-01T085059Z

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T085059Z`
- Source truth: upstream `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_svg`.
- Cluster: SVG paint `fill` / `stroke` advanced color target fallbacks, including `url(#id)` paint servers and custom-property paint prefixes.

## Red Probe

Before the implementation change, this current-base probe failed:

```sh
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); $actual=$p->prefixForTargets(".foo { fill: url(#foo) lch(50.998% 135.363 338) }", ["chrome"=>90,"safari"=>14]); $expected=".foo{fill:url(\"#foo\") #ee00be;fill:url(\"#foo\") color(display-p3 .972962 -.362078 .804206);fill:url(\"#foo\") lch(50.998% 135.363 338)}"; if ($actual !== $expected) { fwrite(STDERR, "RED svg paint fallback mismatch\nactual: $actual\nexpected: $expected\n"); exit(1); }'
```

Observed output kept only `.foo{fill:url(#foo) lch(50.998% 135.363 338)}`.

## Behavior

The pinned upstream `test_svg` cases emit:

- `fill: lch(50.998% 135.363 338)` and `stroke: lch(...)` as sRGB fallback, display-p3 fallback, then the original LCH value for Chrome 90 + Safari 14 targets.
- `fill: url(#foo) lch(...)` with the same fallback stack and upstream URL quoting as `url("#foo")`.
- `fill: var(--url) lch(...)` as an sRGB paint fallback plus an `@supports (color: lab(...))` guarded Lab declaration for Chrome 90.
- No fallback for Safari 15, which already supports the advanced color path covered by this target model.

The PHP prefixer now treats `fill` and `stroke` as advanced-color-capable properties in the existing fallback pass, reusing the same sRGB/P3/Lab conversion tables and URL normalization path as background and outline values.

## Evidence

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-svg-paint-advanced-color-prefixer.php` -> no syntax errors.
- `php lanes/lightningcss/examples/wordpress-svg-paint-advanced-color-prefixer.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 1133 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7003 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> no whitespace errors.

## Status Delta

- Focused lane assertion count moved from `6998` to `7003`.
- `lane-status.json` now records `phpPass: 7003`.
- Mapped denominator remains unchanged because this adds focused assertions for an already mapped upstream `test_svg` family.

## Dependency Closure

No new support component is needed. The native PHP `TransitionPrefixer` advanced-color fallback pass already provides the required color fallback conversion, URL normalization, and guarded Lab support rule generation.

## Non-overlap

This does not repeat the recent basic color minification, font oblique default-angle, grid auto-flow shorthand, unicode-bidi target-prefix, background advanced-color, or outline advanced-color slices. It is limited to upstream SVG paint property fallback parity for `fill` and `stroke`.
