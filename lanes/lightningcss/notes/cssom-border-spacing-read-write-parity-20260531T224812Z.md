# CSSOM Border Spacing Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T224812Z`

Accepted base: `33a65237308053a0654b3629f3bffe8d77c73515`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` exercises generic `DeclarationBlock::get`, `set`, and `remove` behavior through parsed property values.
- `src/declaration.rs` routes CSSOM `set` through `Property::parse_string`, and CSSOM `get` serializes the parsed property value.
- `src/properties/mod.rs` defines `border-spacing` as `BorderSpacing(Size2D<Length>)`.
- `src/values/size.rs` parses one or two length values and serializes equal horizontal/vertical values as one token.

## Red-First Evidence

Before this patch, the PHP DeclarationBlock kept the raw CSSOM string for `border-spacing`:

```text
php -r 'require "tools/bootstrap.php"; $b = new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->getProperty("border-spacing: 0px 0px", "border-spacing"), $b->setProperty("border-spacing: 0px 0px; color: red", "border-spacing", "4px 4px")]); echo "\n";'

array (
  0 =>
  array (
    'value' => '0px 0px',
    'important' => false,
  ),
  1 => 'border-spacing: 4px 4px; color: red',
)
```

The upstream-backed target is `0` for equal zero lengths and `4px` for equal replacement lengths.

## Patch

- Added `DeclarationBlock` normalization for `border-spacing` Size2D values.
- Canonicalizes one-value, two-value, zero-unit, and equal-pair parsed length CSSOM reads/writes.
- Added focused tests in `DeclarationBlockTest.php`.
- Added `wordpress-table-border-spacing-cssom.php` as a table block style CSSOM smoke.
- Updated `lane-status.json` from `4680` to `4687` PHP assertions. Mapped coverage stays `2173 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

## Verification

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-table-border-spacing-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-table-border-spacing-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 819 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-table-border-spacing-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 4687 assertions, 0 failures

php -r '$json = file_get_contents("lanes/lightningcss/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
lane-status.json OK

git diff --check -- lanes/lightningcss
passes with no output
```

## Non-Overlap

This does not repeat the accepted border-spacing minifier slice (`src/lib.rs::test_border_spacing`) or existing CSSOM shorthand clusters for background, border, grid, flex, mask, animation, transition, list-style, text-decoration, font, or container. It targets the simple parsed `border-spacing` property's CSSOM declaration read/write serialization path.

The stale 2026-05-25 CustomMedia import-tail rework note was inspected and is unrelated to this CSSOM micro-slice; no CustomMedia code was touched.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP DeclarationBlock tokenizer and adds a bounded value normalizer for an already parsed CSS property. No Node, Rust, WASM, network service, or external provider dependency is introduced.

## Next Task

Continue CSSOM read/write parity for simple parsed properties whose upstream values serialize differently from raw input, or move to the next supervisor-priority LightningCSS gap in bundle/import graph, CSS Modules, source maps, media queries, target prefixing, property/value parity, or custom at-rules.
