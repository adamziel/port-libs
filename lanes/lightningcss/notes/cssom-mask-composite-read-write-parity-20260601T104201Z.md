# LightningCSS CSSOM Mask Compositing Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T104201Z`

## Upstream Source Truth

- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Relevant upstream files:
  - `src/properties/masking.rs`: `MaskComposite`, `WebKitMaskComposite`, `WebKitMaskSourceType`, `MaskMode`
  - `src/properties/mod.rs`: property registrations for `mask-composite`, `mask-mode`, `-webkit-mask-composite`, and WebKit `mask-source-type`
  - `src/lib.rs::test_mask`: WebKit mask prefix output includes `-webkit-mask-composite: source-out` and `-webkit-mask-source-type: luminance` paired with modern `mask-composite: subtract` and `mask-mode: luminance`

## Red-First Observation

Before this patch, standalone CSSOM declaration reads and writes preserved authored enum casing:

```text
parse("-webkit-mask-composite: SOURCE-OUT, Xor; -webkit-mask-source-type: LUMINANCE; mask-composite: SUBTRACT; mask-mode: LUMINANCE; --Mask-Composite: SOURCE-OUT")
=> -webkit-mask-composite: "SOURCE-OUT, Xor"
=> -webkit-mask-source-type: "LUMINANCE"
=> mask-composite: "SUBTRACT"
=> mask-mode: "LUMINANCE"
```

Custom properties correctly preserved their authored case and remain unchanged by this slice.

## Implemented Behavior

- Canonicalizes standalone comma-separated keyword lists for:
  - `mask-composite`
  - `mask-mode`
  - `-webkit-mask-composite`
  - `-webkit-mask-source-type`
- Reuses existing declaration parsing, top-level comma splitting, and CSSOM read/write/remove behavior.
- Preserves unknown values, invalid list shapes, priority bucket ordering, and custom property case.
- Does not change `mask` shorthand composition or existing mask longhand/shorthand decomposition behavior.

## Verification

Commands run:

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
php -l lanes/lightningcss/tests/DeclarationBlockTest.php
php -l lanes/lightningcss/examples/wordpress-mask-cssom.php
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
php lanes/lightningcss/examples/wordpress-mask-cssom.php --self-test
php tools/run-tests.php lanes/lightningcss/tests
git diff --check -- lanes/lightningcss
```

Results:

```text
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-mask-cssom.php
DeclarationBlockTest.php: 1 test files, 1181 assertions, 0 failures
wordpress-mask-cssom.php --self-test: OK
Full LightningCSS lane: 13 test files, 7444 assertions, 0 failures
git diff --check -- lanes/lightningcss: OK
```

## Dependency Closure

No new support component is needed. The slice reuses the existing `DeclarationBlock` parser, CSSOM declaration normalizer, and top-level token splitter.

## Non-Overlap

This patch does not repeat accepted bundle/import graph, source-map, CSS Modules, media-query, target-prefixing, mask shorthand, WebKit mask box image, mask border, mask type, clip-path, SVG paint, perspective-origin, flex CSSOM, or animation-composition clusters. It is limited to standalone read/write parity for the upstream mask compositing/source-type declaration enums.
