# Media Query Redundant Boolean Wrapper Parity

Date: 2026-06-01
Micro-slice: `lightningcss-media-query-range-layer-parity-20260601T051343Z`
Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source truth

The pinned native LightningCSS binary serializes redundant whole-condition wrappers away when they contain same-operator boolean groups:

- `@media ((hover) and ((color) and (width >= 1px)))` -> `@media (hover) and (color) and (width>=1px)`
- `@media ((hover) or ((color) or (width >= 1px)))` -> `@media (hover) or (color) or (width>=1px)`
- Mixed groups keep the required inner grouping, for example `@media ((hover) and ((color) or (width >= 1px)))` -> `@media (hover) and ((color) or (width>=1px))`
- Explicit media types preserve `or` grouping after the type, for example `screen and ((hover) or ((color) and (width >= 1px)))` -> `screen and ((hover) or ((color) and (width>=1px)))`

Native spot checks also showed standalone `@import` media tails serialize `((pointer: coarse) or (hover: none))` as `(pointer:coarse) or (hover:none)`.

## Implementation

`MediaQueryParser` now recursively flattens redundant boolean groups to a fixed point and collapses top-level condition wrappers with a guard that preserves `or` grouping after explicit media types. This keeps the combined import-graph form `((width>=250px) or (color)) and (orientation:landscape)` while allowing the standalone imported media tail to match upstream as `(width>=250px) or (color)`.

Focused PHP coverage was added for layered media queries, bundled layered import media tails, custom-media import tails, and the WordPress media-layer example.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 437 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 556 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php`
  - `1 test files, 40 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - passed and printed the expected minified CSS
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6144 assertions, 0 failures`

## Status delta

`lane-status.json` now reports `phpPass: 6144` and `phpFail: 0`.

Conservative mapped coverage remains `2353 / 3532` because this deepens already represented media-query, import-graph, and custom-media clusters rather than claiming new denominator rows.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP media parser, bundler, custom-media transformer, and WordPress media-layer smoke path.

## Non-overlap

This does not repeat the accepted media comment-trivia, explicit media-condition validation, all/not-all elision, range fallback target-prefixing, resolution unit, or custom feature name slices. It is specifically the upstream serialization parity for redundant top-level boolean condition wrappers across layered media, import tails, and custom-media-expanded import tails.
