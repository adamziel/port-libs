# CSSOM Shadow Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T001306Z`

Accepted base: `9938ea0ca5f2430c11f7b91d23d2213507185488`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` exercises `DeclarationBlock::get`, `set`, and `remove` through parsed declarations.
- `src/declaration.rs` normalizes parsed declaration values before CSSOM reads/writes and serializes them through the property printers.
- `src/properties/box_shadow.rs` and `src/properties/text.rs` define `box-shadow` and `text-shadow` as parsed declaration values. The printers move `inset` before box-shadow lengths, drop default `currentColor`, omit zero blur/spread components where allowed, and serialize colors through the same shortened color printer used by LightningCSS minification.

## Red-First Evidence

Before this patch, DeclarationBlock stored shadow declarations as opaque values even though the upstream property printers canonicalize them:

```text
php -r 'require "tools/bootstrap.php"; $b=new PortLibs\LightningCSS\DeclarationBlock(); foreach (["box-shadow: 12px 12px 0px 0px rgba(0,0,0,0.4)", "text-shadow: 1px 1px 0 yellow"] as $css) { var_export($b->parse($css)); echo "\n"; } echo $b->setProperty("color: red", "box-shadow", "0px 0px 12px 4px rgba(0,0,0,0.4) inset"), "\n";'

array (
  'box-shadow' => '12px 12px 0px 0px rgba(0,0,0,0.4)',
)
array (
  'text-shadow' => '1px 1px 0 yellow',
)
color: red; box-shadow: 0px 0px 12px 4px rgba(0,0,0,0.4) inset
```

The upstream-backed target is to make CSSOM declaration reads, writes, and removals expose the canonical `box-shadow` / `text-shadow` serialization already used by native property parsing.

## Patch

- Added DeclarationBlock normalization for `box-shadow`, `-webkit-box-shadow`, `-moz-box-shadow`, and `text-shadow`.
- Canonicalizes shadow layer lists by dropping zero spread/blur components, moving `inset` before box-shadow lengths, omitting explicit `currentColor`, shortening named colors such as `yellow` and `blue`, and compressing `rgb()` / `rgba()` colors to hex when bounded parsing succeeds.
- Added focused CSSOM tests for parse, get, set, important writes, prefixed box-shadow writes, direct removal, color shortening, default currentColor omission, and comma-separated text-shadow layers.
- Added `wordpress-shadow-cssom.php` to cover block/theme editor shadow token reads, overrides, and removal without Node/WASM.
- Updated `lane-status.json` from `5016` to `5024` PHP assertions. Mapped coverage remains conservative at `2218 / 3532` because this deepens the already represented CSSOM declaration cluster.

## Verification

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-shadow-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-shadow-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 867 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-shadow-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 5024 assertions, 0 failures

php -r '$json = file_get_contents("lanes/lightningcss/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
lane-status.json OK

git diff --check -- lanes/lightningcss
passes with no output
```

## Non-Overlap

This does not repeat accepted shadow minifier or target-prefixing slices. Those already cover stylesheet minification and browser fallback emission. This patch is specifically CSSOM DeclarationBlock read/write parity for direct shadow declaration values.

It also avoids the accepted CSSOM border, border-spacing, background, mask, animation, transition, list-style, text-decoration, text-emphasis, caret, font, container, logical sizing, custom at-rule, media-query, source-map, bundle/import graph, and CSS Modules clusters.

The stale 2026-05-25 CustomMedia import-tail rework note was inspected and is unrelated to this CSSOM micro-slice; no CustomMedia code was touched.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP DeclarationBlock parser, CSS comment/top-level token splitters, numeric normalizer, and serializer. No Node, Rust, WASM, network service, or external provider dependency is introduced.

## Next Task

Continue CSSOM read/write parity for remaining parsed declaration-value clusters, or move to the next supervisor-priority LightningCSS gap in bundle/import graph, CSS Modules, source maps, media queries, target prefixing, property/value parity, or custom at-rules.
