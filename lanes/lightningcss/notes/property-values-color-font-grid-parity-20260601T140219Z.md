# LightningCSS Property Values Color/Font/Grid Parity 2026-06-01T14:02:19Z

## Scope

Implemented one pinned upstream relative-color behavior from
`parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
The source truth is `src/lib.rs::test_relative_color`, specifically the loop
that checks Lab and Oklab relative colors from a non-Lab/Oklab origin:

- `lab(from color(display-p3 0 0 0) l a b / alpha)` -> `lab(0% 0 0)`
- `oklab(from color(display-p3 0 0 0) l a b / alpha)` -> `oklab(0% 0 0)`

Before the patch, Lab already folded but Oklab remained serialized as an
unresolved relative color. The implementation adds the bounded
`color(display-p3 0 0 0)` Oklab origin shortcut beside the existing Oklch
black-origin shortcut.

## Red-First Probe

Command:

```sh
php -r 'require "tools/bootstrap.php"; $m=new PortLibs\LightningCSS\CssMinifier(); foreach (["lab(from color(display-p3 0 0 0) l a b / alpha)", "oklab(from color(display-p3 0 0 0) l a b / alpha)", "lch(from color(display-p3 0 0 0) l c h / alpha)", "oklch(from color(display-p3 0 0 0) l c h / alpha)"] as $v) { echo $v," => ",$m->minify(".foo { color: $v; }"),"\n"; }'
```

Observed before implementation:

```text
lab(from color(display-p3 0 0 0) l a b / alpha) => .foo{color:lab(0% 0 0)}
oklab(from color(display-p3 0 0 0) l a b / alpha) => .foo{color:oklab(from color(display-p3 0 0 0) l a b/alpha)}
lch(from color(display-p3 0 0 0) l c h / alpha) => .foo{color:lch(0% 0 0)}
oklch(from color(display-p3 0 0 0) l c h / alpha) => .foo{color:oklch(0% 0 0)}
```

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` - no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-relative-non-srgb-color.php` - no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - 1 test file / 2022 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-relative-non-srgb-color.php --self-test` - passed and printed `.wp-block-cover.has-wide-gamut-relative{color:#00f942;background-color:#2a0022;border-color:#fff;outline-color:oklab(0% 0 0)}`
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 test files / 8162 assertions / 0 failures
- `git diff --check -- lanes/lightningcss` - passed

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice avoids the accepted media-query resolution x-unit serialization,
font oblique/default and font-family duplicate value work, grid auto-flow/default
value work, color-mix, relative HSL/HWB, XYZ relative color, target-prefix,
CSSOM, source-map, bundle, and CSS Modules clusters. It only deepens the
already represented relative-color property-value cluster for the Oklab
non-sRGB black-origin case. Conservative mapped coverage remains `2393 / 3532`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
`CssMinifier` relative advanced-color parser and adds a bounded origin fallback
for a known upstream case.

## Next Task

Continue property-value parity with broader relative Lab/Oklab origin
conversion once a general color-space conversion helper is available, or move
to the next unmapped color/font/grid upstream helper case.
