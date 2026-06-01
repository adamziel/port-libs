# CSSOM Size Value Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T122256Z`

Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

Source truth:

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` routes `DeclarationBlock::get`, `set`, and `remove` through parsed `Property` values and `ToCss` serialization.
- `src/properties/mod.rs` maps `width`, `height`, logical size longhands, min-size longhands, and max-size longhands to typed size properties.
- `src/properties/size.rs` defines canonical `Size` and `MaxSize` output for sizing keywords, prefixed intrinsic-size keywords, `fit-content(...)`, and length/percentage values.

Implementation:

- `DeclarationBlock` now canonicalizes CSSOM size declaration values for:
  - `width`, `height`, `block-size`, `inline-size`
  - `min-width`, `min-height`, `min-block-size`, `min-inline-size`
  - `max-width`, `max-height`, `max-block-size`, `max-inline-size`
- Preferred/min-size properties canonicalize `auto`, intrinsic keywords, prefixed intrinsic keywords, `stretch`, `contain`, `fit-content(...)`, and simple length/percentage tokens.
- Max-size properties canonicalize the same size value cluster with `none` instead of `auto`.
- Custom properties remain raw and case-preserving.
- The existing WordPress logical-size CSSOM smoke now also covers canonical size values used by block-theme layout constraints.

Evidence:

- Red-first probe before implementation returned raw authored values such as `MIN-CONTENT`, `FIT-CONTENT(100.0%)`, and `width: FIT-CONTENT(100.0%)`.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1206 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7706 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-logical-size-cssom.php --self-test`
  - `OK`

Status delta:

- Full LightningCSS PHP lane assertions move from `7694` to `7706` (`+12`).
- Conservative mapped coverage remains `2374 / 3532`; this deepens the existing DeclarationBlock CSSOM cluster.

Dependency closure:

- No new support component is needed. This reuses the lane-local native PHP DeclarationBlock parser and existing length/percentage serializers. No Rust, Node, WASM, network, provider, or live-service runner is required.

Non-overlap:

- This does not repeat accepted logical-size write-order, transform, flex, direct enum, display/layout, font, container, view-transition, mask, border, or shorthand CSSOM slices.
- It is limited to typed size value serialization during CSSOM declaration read/write/remove paths.
