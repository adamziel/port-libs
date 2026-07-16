# CSSOM Declaration Write Block Guard Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T205458Z`

Accepted base: `7a6ad881ab7ec5dade7133aeca014b7a5e54577c`

Upstream source truth:

- `parcel-bundler/lightningcss` pinned cache commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::parse_declaration` parses a property id, then stops non-custom declarations before a top-level `{` token while allowing custom-property blocks.
- `tests/test_cssom.rs::test_set` exercises `DeclarationBlock::set` through already-parsed `Property::parse_string(...)`, so invalid non-custom declaration values cannot be written through upstream CSSOM set paths.

## Behavior

- `DeclarationBlock::setProperty()` now rejects non-custom property values containing a top-level `{...}` block, matching the parser guard already used by `parseEntries()`.
- Custom properties still accept token-block values such as `{ color: blue; background: url("/a;b.css") }`.
- Function-contained block tokens such as `var(--theme-rule, { color: blue; })` remain accepted because they are not a top-level declaration block.
- The WordPress custom-property CSSOM smoke now verifies that block-style custom design tokens still round-trip while invalid nested style blocks cannot be written into normal declarations.

## Evidence

- Red-first before implementation:
  `php -r 'require "tools/bootstrap.php"; $b=new PortLibs\\LightningCSS\\DeclarationBlock(); try { echo $b->setProperty("color: red", "color", "{ color: blue; }"); echo "\\naccepted-invalid\\n"; exit(1); } catch (InvalidArgumentException $e) { echo "rejected\\n"; }'`
  exited `1` and printed `accepted-invalid`.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`:
  `1 test files, 748 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`:
  `13 test files, 4312 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-property-block-cssom.php --self-test`:
  `OK`.
- `php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-custom-property-block-cssom.php`:
  no syntax errors.
- `git diff --check -- lanes/lightningcss`:
  passed.

## Coverage

- Full LightningCSS PHP evidence moves from `4308` to `4312` assertions.
- Conservative mapped coverage remains `2100 / 3532`; this deepens the already represented upstream `DeclarationBlock` CSSOM parse/set cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local declaration parser, tokenizer, and existing top-level block scanner; no Node, Rust, WASM, external provider, or new shared dependency is required.

## Non-Overlap

This avoids the stale 2026-05-25 `CustomMediaTransformer` rework note and does not repeat accepted CSSOM priority buckets, property locations, shorthand removal, custom-property block parsing, WebKit mask-box-image, mask, background, grid, flex, or target-prefix slices. The patch is limited to write-time non-custom declaration value validation.
