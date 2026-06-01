# CSSOM Direct Font Longhand Read/Write Parity

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T135307Z`
- Base accepted HEAD: `3d3fd16a1ad2e27200a3709363c7e0cf6167b424`
- Upstream source truth: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `tests/test_cssom.rs` exercises `DeclarationBlock::{get,set,remove}` by parsing expected values through `Property::parse_string` and serializing set results with `to_css_string`.
- `src/properties/mod.rs` maps direct font longhand declarations to typed property parsers for `font-family`, `font-size`, `line-height`, `font-weight`, `font-stretch`, and `font-variant-caps`.
- `src/properties/font.rs` implements parse and serializer paths for those longhands, so direct CSSOM values use the same canonical forms as shorthand-derived longhands.

## Red-First Probe

Before the source change, the new focused test failed because the native PHP direct declaration path preserved raw font longhand token text instead of serializing through the upstream-shaped canonicalizer:

```text
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php

FAIL declaration block canonicalizes upstream direct font longhand cssom declarations

Expected normalized values:
font-family => Inter var, system-ui
font-size => 16px
line-height => 1.5
font-weight => 700
font-stretch => 125%
font-variant-caps => all-small-caps

Actual values before the patch preserved raw tokens:
font-family => "Inter var", system-ui
font-size => +016.00PX
line-height => +001.500
font-weight => +0700
font-stretch => 125.0%
font-variant-caps => All-Small-Caps

1 test files, 1242 assertions, 1 failures
```

## Implementation

- Routed direct font longhand declaration reads and writes through the existing native PHP font longhand normalizer.
- Added direct `font-size` normalization for length and percentage values while lowercasing upstream keyword values.
- Added direct `line-height` normalization for unitless numbers, lengths, percentages, and `normal`.
- Extended direct `font-weight` numeric normalization so values like `+0700` serialize as `700`.
- Extended direct `font-stretch` percentage normalization so values like `125.0%` and `+087.500%` serialize without redundant numeric text.
- Preserved custom property token streams; `--Font-Size: +016.00PX` remains raw.
- Extended the WordPress font CSSOM smoke with direct typography longhand parse and direct setProperty cases.

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-font-cssom.php` -> no syntax errors
- `php lanes/lightningcss/examples/wordpress-font-cssom.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1254 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8164 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` -> passed

## Non-Overlap

This slice deepens the represented DeclarationBlock CSSOM font cluster with direct longhand normalization. It does not repeat the accepted font shorthand, font-style oblique angle, font-palette dashed-ident, legacy flex, SVG stroke-linejoin miter-clip, source-map, bundle/import graph, CSS Modules, custom at-rule, media-query, or target-prefixing slices. Conservative mapped coverage remains `2393 / 3532`.

## Dependency Closure

No new support component is needed. The behavior reuses the existing native PHP `DeclarationBlock` parser, font longhand serializer helpers, and WordPress font CSSOM smoke.
