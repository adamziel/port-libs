# Pandoc Doctemplate Digit-Leading Metadata Keys

Slice: `pandoc-doctemplates-core-current-base-20260608T055737Z`
Base: `01048f98727ca2e231e798c72d6a8093d9f4eefd`

## Behavior

- Native `DocTemplate` now accepts digit-leading child metadata field segments such as `article.2026-review`, `it.1st-pass`, and `assets.360-view`.
- Top-level template directive names remain guarded: the first path segment still needs to be letter-led except for the existing `$it$` loop binding, and reserved control words remain invalid as variable path segments.
- The WordPress doctemplate review-packet smoke now exercises digit-leading imported metadata keys and applied-partial rebinding over an `assets.360-view` field.

## Source Truth And Non-Overlap

- This is bounded support-library work under `lanes/pandoc/src/DocTemplate.php`.
- It does not overlap the accepted doctemplate map-pairs, applied-partial rebinding, breakable-space wrapping, braced separator, default template fallback, Beamer/man/ms fallback, or extension-qualified output-format slices.
- Red-first evidence before implementation:
  `UnexpectedValueException: Unsupported doctemplate directive article.2026-review.status at <template>:1:1`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  `1 test files, 649 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  `OK wordpress doctemplate review packet`
- PHP lint, JSON validation, and whitespace checks passed before handoff.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native doctemplate parser, resource renderer, partial rebinding, focused DocTemplate tests, and the lane-local WordPress doctemplate example. No Pandoc, Cabal/Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.
