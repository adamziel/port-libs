# CSSOM CSS-Wide Keyword Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T232158Z`

Accepted base: `afee0853cdadd52fa12dbc1e24d633ac7329910c`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::get` returns parsed `Property` values and serializes them through `ToCss`.
- `src/declaration.rs::DeclarationBlock::set` receives values parsed by `Property::parse_string(...)`.
- `src/properties/mod.rs` parses CSS-wide keywords as normal parsed declaration values, not as raw case-preserving strings.
- Custom properties remain token-preserving, so this slice intentionally leaves `--Block-Reset: InHeRiT` unchanged.

## Red-First Evidence

Before this patch, normal non-custom declarations preserved raw CSS-wide keyword casing:

```text
php <<'PHP'
<?php
require 'tools/bootstrap.php';
$b = new PortLibs\LightningCSS\DeclarationBlock();
var_export($b->getProperty('color: InHeRiT', 'color'));
echo "\n";
echo $b->setProperty('color: red', 'color', 'ReVeRt-LaYeR'), "\n";
var_export($b->getProperty('border-spacing: ReVeRt', 'border-spacing'));
echo "\n";
PHP

array (
  'value' => 'InHeRiT',
  'important' => false,
)
color: ReVeRt-LaYeR
array (
  'value' => 'ReVeRt',
  'important' => false,
)
```

## Patch

- Generalized `DeclarationBlock::normalizeDeclarationValue()` so every non-custom property canonicalizes whole-value CSS-wide keywords to lowercase before property-specific normalization.
- Preserved custom-property token casing for WordPress design-token fallbacks.
- Added focused read, set, remove, priority-bucket, shorthand, and `border-spacing` assertions in `DeclarationBlockTest.php`.
- Added `wordpress-css-wide-keyword-cssom.php` for WordPress theme/editor reset CSSOM smoke coverage.
- Updated `lane-status.json` from `4821` to `4831` PHP assertions. Mapped coverage remains conservative because this deepens the already represented DeclarationBlock CSSOM cluster.

## Verification

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-css-wide-keyword-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-css-wide-keyword-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 841 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-css-wide-keyword-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 4831 assertions, 0 failures

php -r '$json = file_get_contents("lanes/lightningcss/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
lane-status.json OK

git diff --check -- lanes/lightningcss
passes with no output
```

## Non-Overlap

This does not repeat the accepted `all`-only CSS-wide keyword slice or the accepted `border-spacing` Size2D normalizer. It broadens the same upstream CSSOM declaration read/write surface to all non-custom properties while preserving custom-property token values.

The stale 2026-05-25 `CustomMediaTransformer` import-tail rework note was inspected and is unrelated to this CSSOM micro-slice; no CustomMedia code was touched.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP DeclarationBlock parser, tokenizer, and CSSOM mutation path. No Node, Rust, WASM, network, provider, or shared support dependency is introduced.

## Next Task

Continue CSSOM read/write parity for parsed declaration values whose upstream serialization differs from raw input, or move to the next supervisor-priority LightningCSS gap in bundle/import graph, CSS Modules, source maps, media queries, target prefixing, property/value parity, or custom at-rules.
