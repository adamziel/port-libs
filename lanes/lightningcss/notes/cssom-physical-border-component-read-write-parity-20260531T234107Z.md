# CSSOM Physical Border Component Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T234107Z`

Accepted base: `6dcdbdf63680f15710c0b63f093637566ee78a22`

## Source Truth

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` exercises `DeclarationBlock::get`, `set`, and `remove` through parsed declarations.
- `src/declaration.rs` walks declarations in reverse for CSSOM `set`, replaces exact longhands, updates compatible shorthand declarations through `decl.set_longhand()`, and stops when logical/physical border groups conflict.
- `src/properties/border.rs` and `src/macros.rs` define `border-color`, `border-width`, `border-style`, and side-border shorthands as component shorthands that can absorb matching physical longhand updates. Full `border` remains non-updatable for a single side component and therefore appends the longhand.

## Red-First Evidence

Before this patch, the PHP DeclarationBlock could read expanded physical border components but appended writes that upstream stores in compatible shorthands:

```text
php -r 'require "tools/bootstrap.php"; $b=new PortLibs\LightningCSS\DeclarationBlock(); foreach ([[$b->setProperty("border-color: red green", "border-top-color", "blue"), "border-color top"], [$b->setProperty("border-top: 1px solid red", "border-top-color", "blue"), "border-top color"], [$b->getProperty("border-top: 1px solid red", "border-top"), "get border-top"], [$b->getProperty("border-top: 1px solid red", "border-top-color"), "get border-top-color"]] as [$v,$label]) { echo $label, ": ", var_export($v,true), "\n"; }'

border-color top: 'border-color: red green; border-top-color: blue'
border-top color: 'border-top: 1px solid red; border-top-color: blue'
get border-top: array (
  'value' => '1px solid red',
  'important' => false,
)
get border-top-color: array (
  'value' => 'red',
  'important' => false,
)
```

The upstream-backed target is to update `border-color`, `border-width`, `border-style`, and side-border shorthands in place when priority and logical group ordering allow it.

## Patch

- Added `DeclarationBlock::setBorderLonghand()` for physical `border-*-width`, `border-*-style`, and `border-*-color` writes.
- Preserves reverse declaration search, exact longhand replacement, priority matching for shorthand mutation, and logical/physical border conflict barriers.
- Updates compatible `border-color` / `border-width` / `border-style` box shorthands and `border-top` / `border-right` / `border-bottom` / `border-left` side shorthands without decomposing full `border`.
- Added focused DeclarationBlock tests for reads, shorthand mutation, side shorthand mutation, latest-compatible declaration selection, logical conflict appending, and cross-priority appending.
- Extended `wordpress-border-cssom.php` with block editor border token smoke coverage for CSSOM component writes.
- Updated `lane-status.json` from `4877` to `4887` PHP assertions. Mapped coverage stays conservative because this deepens the already represented DeclarationBlock CSSOM cluster.

## Verification

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-border-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-border-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 849 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-border-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 4887 assertions, 0 failures

php -r '$json = file_get_contents("lanes/lightningcss/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
lane-status.json OK

git diff --check -- lanes/lightningcss
passes with no output
```

## Non-Overlap

This does not repeat the accepted CSSOM logical border, background, border-spacing, multi-column, grid, flex, mask, animation, transition, list-style, text-decoration, font, container, custom at-rule, media-query, or target-prefixing slices. It targets physical border component writes into existing CSSOM border component shorthands.

The stale 2026-05-25 CustomMedia import-tail rework note was inspected and is unrelated to this CSSOM micro-slice; no CustomMedia code was touched.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP DeclarationBlock parser, box shorthand expansion/compression, border component parser, and serializer. No Node, Rust, WASM, network service, or external provider dependency is introduced.

## Next Task

Continue CSSOM read/write parity for remaining parsed declaration clusters, or move to the next supervisor-priority LightningCSS gap in bundle/import graph, CSS Modules, source maps, media queries, target prefixing, property/value parity, or custom at-rules.
