# Pandoc XML/HTML5 DOM Core Current Base - Element Referrer Policy Metadata

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T224229Z`
Base accepted HEAD: `fb68aedd3080f5c5d86cf57108d39e4c2a7b6359`

## Behavior

- `Html5DomFragment` now converts valid HTML `referrerpolicy` values on passive `<link>` review anchors, ordinary `<a>` links, `<img>` elements, and image-map `<area>` handoff links into inert `data-pandoc-referrerpolicy` metadata.
- Invalid element referrer-policy values emit `unsafe-attribute` diagnostics and are not serialized into WordPress raw HTML handoff output.
- Fragment summaries now count `referrer-policy-review` diagnostics under `filteredAttributes`, so reviewers can see that live browser policy attributes were transformed.
- The WordPress HTML5 DOM fragment smoke now covers a visible source link with `referrerpolicy=" Strict-Origin "` and asserts that live `referrerpolicy=` is absent from the final blocks.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before work began.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` failed with `1 test files, 1628 assertions, 1 failures` because live `referrerpolicy` attributes still serialized.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1645 assertions, 0 failures`.
- Adjacent DOM family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 1960 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- JSON metadata validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lane-status.json` `phpPass`: `1936 -> 1937`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2357 -> 2358`.
- `inventory.xmlHtmlDomCoreCases`: `8 -> 9`.
- `inventory.mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `inventory.xmlHtmlDomCoreAssertions`: `124 -> 142`.
- Added `inventory.mappedXmlHtmlDomReferrerPolicyCases: 1`.

## Dependency Closure

No new support component is needed. The patch reuses native `Html5DomFragment`, DOM/libxml `NONET` parsing, existing safe URL normalization, the established iframe/meta referrer-policy token allowlist, `AstNode` raw HTML handoff, `WordPressBlockWriter`, focused PHP tests, and the local WordPress handoff example. No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML parser, external sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM slices for iframe policy metadata, meta `name=referrer` review spans, passive link relation conversion, image map link conversion, navigation side-effect stripping, base URL handling, SVG/MathML resource filtering, hidden/inert/dialog/popover metadata, microdata/RDFa metadata, source-line diagnostics, or form/value metadata. This slice only owns element-level `referrerpolicy` conversion into inert reviewer metadata for already-safe link/media handoff surfaces.
