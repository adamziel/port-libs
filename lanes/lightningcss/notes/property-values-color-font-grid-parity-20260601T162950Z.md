# Property Values Color/Font/Grid Parity - 2026-06-01T16:29:50Z

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T162950Z`

## Source Truth

- Upstream pinned LightningCSS checkout:
  `/home/claude/port-libs/.upstream-cache/lightningcss` at
  `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Focused upstream rows: `src/lib.rs::test_font` non-minified `test(...)`
  formatter cases for:
  - composing `font-family`, `font-size`, `font-weight`, `font-style`,
    `font-stretch`, `font-variant-caps: small-caps`, and `line-height` to one
    pretty `font` shorthand;
  - preserving unsupported `font-variant-caps: all-small-caps` as a separate
    longhand while composing the remaining font longhands;
  - folding a simple later `line-height` into an existing `font` shorthand;
  - preserving variable `line-height` as a guarded longhand while still
    formatting the `font` family list.

## Red-First Probe

Before the patch, the PHP formatter printed the upstream longhand row as
individual declarations:

```sh
php <<'PHP'
<?php
require 'tools/bootstrap.php';
$f = new PortLibs\LightningCSS\CssFormatter();
echo $f->format('.foo{font-family:"Helvetica","Times New Roman",sans-serif;font-size:12px;font-weight:bold;font-style:italic;font-stretch:expanded;font-variant-caps:small-caps;line-height:1.2em}');
PHP
```

Output started with separate `font-family`, `font-size`, `font-weight`,
`font-style`, `font-stretch`, `font-variant-caps`, and `line-height`
declarations instead of upstream's composed `font` shorthand.

## Changes

- Added conservative style-rule font formatting in `CssFormatter`:
  - contiguous font longhands compose to a pretty `font` shorthand when
    `font-family` and `font-size` are present;
  - `small-caps` participates in the shorthand, while `all-small-caps` remains
    a separate `font-variant-caps` declaration;
  - simple `line-height` folds into `font`, while variable/function-backed
    line-height guards remain separate;
  - safe quoted author font family names unquote in formatter output, and
    family lists serialize with upstream-style comma spacing.
- Added four focused `CssFormatterTest` assertions for the upstream
  `test_font` formatter cluster.
- Added `examples/wordpress-font-formatter.php` for block typography CSS that
  exercises longhand composition, line-height folding, and variable
  line-height preservation without Node/Rust/WASM.
- Updated `lane-status.json` for the focused assertion delta:
  `phpPass` target `8694 -> 8698`. Mapped coverage remains conservatively
  `2398 / 3532` because this deepens the already represented upstream
  property-value/font formatter cluster.

## Verification

- `php -l lanes/lightningcss/src/CssFormatter.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssFormatterTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-font-formatter.php` -> no
  syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssFormatterTest.php` ->
  `1 test files, 20 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-font-formatter.php --self-test`
  -> exited `0` and printed the expected formatted WordPress typography CSS.
- `git diff --check -- lanes/lightningcss` -> passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted font-family minifier rows, font shorthand
minifier/default omission, font-face src/range descriptors, font-stretch range
normalization, oblique angle identity/radian conversion, font target fallback
boundaries, grid formatter/minifier/CSSOM clusters, basic/advanced color
minification, relative color, color-mix, target-prefixing, media-query,
source-map, CSS Modules, bundle/import graph, selector, parser recovery, or
custom at-rule slices. It only closes the upstream `test_font` pretty-printer
font shorthand composition behavior.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`CssFormatter` style-rule parser, declaration splitter, and focused PHP test
harness. It does not require Node, Rust, WASM, network access, or external
provider credentials.

## Follow-Up

Continue property-value parity only on remaining upstream-backed formatter,
minifier, CSSOM, or target-prefix rows that are not already represented by
focused PHP assertions, or move to the supervisor-priority LightningCSS gaps in
source maps, bundle/import graph, CSS Modules, media-query recovery, custom
at-rules, selectors, and parser recovery.
