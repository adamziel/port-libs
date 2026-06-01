# LightningCSS CSSOM Legacy Flex Group Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T125915Z`

## Upstream Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` registers `box-flex-group` as `BoxFlexGroup(CSSInteger, VendorPrefix) / WebKit unprefixed: false`.
- `src/values/number.rs` defines `CSSInteger` parsing and serialization through cssparser, so CSSOM reads/writes should canonicalize signed integer tokens such as `+004` to `4`.

## Implementation

- `DeclarationBlock` now treats `-webkit-box-flex-group` as an integer-valued legacy flex declaration alongside `-webkit-box-ordinal-group`, `order`, and `-ms-flex-order`.
- Focused CSSOM assertions now cover parse, `getProperty()`, `setProperty()`, and `removeProperty()` for `-webkit-box-flex-group`.
- Updated `examples/wordpress-flex-cssom.php` so the WordPress flex CSSOM smoke includes legacy WebKit flex-group canonicalization for older WebView/editor CSS fallback handling.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1226 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-flex-cssom.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7930 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This reuses the lane-local `DeclarationBlock` parser, integer literal normalizer, priority buckets, and direct declaration serializer.

## Non-Overlap

This slice only adds the missing WebKit-only 2009 flex `box-flex-group` CSSOM integer path. It does not repeat accepted direct/legacy flex flow, flex shorthand, old flex keyword, `-webkit-box-flex`, `-webkit-box-ordinal-group`, `-ms-flex-*`, transition, animation, grid, background, mask, source-map, CSS Modules, bundle/import, media-query, custom-at-rule, property-value, or target-prefixing clusters. Full upstream Rust/Node/WASM runners were not run in this isolated lane.
