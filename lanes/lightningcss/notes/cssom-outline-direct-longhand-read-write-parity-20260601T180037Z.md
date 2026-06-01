# CSSOM Outline Direct Longhand Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T180037Z`

Base accepted HEAD: `eaf4be71f1e017e55035a4ef726a86e2aab9b7cc`

## Source Truth

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` maps `outline-style` to `OutlineStyle` and `outline-width` to `BorderSideWidth`.
- `src/properties/outline.rs` parses `OutlineStyle` as `auto` or a border `LineStyle`; `src/properties/border.rs` parses `BorderSideWidth` keyword or typed length values. Upstream CSSOM serializes parsed properties through `ToCss`, so authored casing and redundant numeric length forms are canonicalized.

## Red-First Probe

Before this patch, direct outline CSSOM longhands preserved authored forms:

```text
parse("outline-style: Dashed; outline-width: +002.000px") => outline-style: Dashed; outline-width: +002.000px
getProperty(..., "outline") => +002.000px dashed red
setProperty("color: red", "outline-style", "DASHED") => color: red; outline-style: DASHED
```

## Implementation

- `DeclarationBlock::normalizeDeclarationValue()` now routes `outline-width`, `outline-style`, and `outline-color` through the outline longhand normalizer.
- `outline-width` now canonicalizes `thin | medium | thick` casing plus length tokens such as `+002.000px` to `2px`.
- `outline-style` now canonicalizes the upstream keyword set without lowercasing unknown/unparsed values.
- `outline-color` reuses the direct color normalizer so shorthand composition and direct longhand parsing agree.
- The WordPress outline CSSOM smoke now covers direct outline longhand parse/read and appending normalized direct outline declarations.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
=> 1 test files, 1366 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-outline-cssom.php --self-test
=> OK

php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-outline-cssom.php
=> no syntax errors detected

php tools/run-tests.php lanes/lightningcss/tests
=> 13 test files, 8903 assertions, 0 failures

git diff --check -- lanes/lightningcss
=> passed with no output
```

## Status Delta

- Focused `DeclarationBlockTest.php` assertions move `1359 -> 1366` (`+7`).
- Full lane-local PHP assertions move `8896 -> 8903` (`+7`).
- Conservative mapped coverage remains `2399 / 3532`; this deepens the already represented DeclarationBlock CSSOM outline cluster.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP declaration parser, existing CSS length serializer, direct color normalizer, outline shorthand composer, and lane-local PHP test harness.

## Non-Overlap

This does not repeat accepted outline shorthand splitting/removal, direct background longhand CSSOM, target-prefixing scroll-navigation pseudo boundaries, source-map, bundle/import graph, CSS Modules, custom-at-rule, media-query, selector, parser recovery, or property-value minifier work. The patch is limited to direct `outline-*` CSSOM longhand canonicalization during parse/get/set paths.
