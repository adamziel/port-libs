# LightningCSS Media Query Custom Media Escaped Ident Range/Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T145959Z`

Base accepted HEAD: `18f15e85d9a0377d81ffec56f3600032eb750869`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Source references:
  - `src/rules/custom_media.rs`: `CustomMediaRule.name` parses and prints a `DashedIdent`.
  - `src/values/ident.rs`: `DashedIdent::parse` accepts CSS identifiers after escape decoding when the decoded identifier starts with `--`.
  - `src/media_query.rs`: custom media feature references are stored as `MediaFeatureName::Custom(DashedIdent(...))` and looked up by the decoded dashed identifier.

## Behavior

The PHP custom media transformer now parses `@custom-media` names and `(--custom-media)` references with the existing CSS identifier tokenizer instead of ASCII-only regex checks. This preserves upstream behavior for escaped dashed identifiers such as `--wp\2d wide`, which decode to the canonical custom media name `--wp-wide` before definition storage, lookup, undefined diagnostics, and circular-reference checks.

This slice specifically covers layered WordPress block CSS paths where escaped custom-media breakpoints appear in:

- `@custom-media --wp\2d wide (width >= 782px);`
- `@media (--wp\2d wide) and (hover) { ... }`
- `@import url(...) layer(theme.blocks) (--wp\2d wide);`

The behavior deepens the existing media-query/custom-media range-layer cluster. Conservative mapped coverage remains `2393 / 3532`.

## Red-First Evidence

Before this patch, the PHP transformer rejected escaped custom-media definitions with:

`InvalidArgumentException Invalid @custom-media rule: --w\69 de (min-width:782px)`

Escaped references to normally declared aliases also failed to resolve because reference collection and substitution only accepted `--[-_a-zA-Z0-9]+`.

## Verification

- `php -l lanes/lightningcss/src/CustomMediaTransformer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/CustomMediaTransformerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-custom-media-transformer.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php`
  - `1 test files, 47 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomMediaTransformerTest.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `2 test files, 717 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-media-transformer.php --self-test`
  - exited `0`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8332 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - passed

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses `CustomMediaTransformer`'s existing CSS escape decoder and identifier tokenizer, which are already exercised for escaped import sources and modifier identifiers.

## Non-Overlap

This does not repeat the accepted resolution `x` unit serialization, redundant calc range lowering, comment trivia, bare-not validation, explicit condition/layer parsing, or media range fallback slices. It only changes custom media definition/reference identifier parsing so escaped dashed aliases resolve through already implemented media query range/layer normalization.

## Next Task

Continue media-query parity on unresolved upstream behavior that is not already represented, especially custom media or import graph cases that require parser-level validation rather than standalone normalization.
