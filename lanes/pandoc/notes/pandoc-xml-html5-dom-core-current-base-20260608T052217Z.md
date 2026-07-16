# Pandoc XML/HTML5 DOM Core Current Base - Popover Review Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T052217Z`
Base: `c162e5af21915b05e444923d010d6e56dffee14f`
Date: 2026-06-08 UTC

## Source Truth

- The XML/HTML5 DOM lane owns bounded safe XML/HTML fragment parsing and serializer behavior needed by document readers and WordPress raw HTML handoff.
- WHATWG HTML defines popover as a live HTML behavior with `auto`, `manual`, and `hint` states, and `popovertarget` / `popovertargetaction` as active invoker attributes. Source checked: <https://html.spec.whatwg.org/multipage/popover.html> and <https://html.spec.whatwg.org/multipage/form-elements.html#attr-button-popovertarget>.
- This slice preserves imported popover content for review while preventing live popover UI behavior from surviving into serialized handoff HTML.

## Implementation

- `Html5DomFragment` now converts HTML `popover` attributes into inert `data-pandoc-popover-state` metadata.
- Empty `popover` normalizes to `auto`; valid `auto`, `manual`, and `hint` states are preserved as metadata.
- Invalid popover states emit an `unsafe-attribute` diagnostic and fall back to `manual` metadata.
- `popovertarget` and `popovertargetaction` are stripped as active invoker attributes.
- The WordPress HTML5 DOM fragment handoff example now includes a popover review packet with safe relative-link resolution and unsafe-link stripping.

## Evidence

- No lane rework note existed at `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline focused test before this slice: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1117 assertions, 0 failures`.
- Red-first probe failed as expected because live `popover`, `popovertarget`, and `popovertargetaction` attributes still survived serialization before implementation.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1143 assertions, 0 failures`.
- Adjacent DOM family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 1431 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for `Html5DomFragment.php`, `Html5DomFragmentTest.php`, and `wordpress-html5-dom-fragment-handoff.php`.
- JSON metadata validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Mapping Delta

- `lane-status.json` `phpPass`: `1542 -> 1543`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1963 -> 1964`.
- `inventory.xmlHtmlDomCoreCases`: `7 -> 8`.
- `inventory.mappedXmlHtmlDomCoreCases`: `7 -> 8`.
- `inventory.xmlHtmlDomCoreAssertions`: `103 -> 129`.
- Added `inventory.mappedXmlHtmlDomPopoverCases: 1`.

## Dependency Closure

No new support component is needed. The slice reuses native `Html5DomFragment`, DOM/libxml `NONET` parsing, lane-local safe URL and attribute normalization, `AstNode` raw HTML handoff, `WordPressBlockWriter`, focused PHP tests, and the local WordPress handoff example. No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML parser, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat DTD/entity rejection, XML declarations, processing instructions, comment-boundary normalization, raw text/RCDATA/plaintext handling, SVG/MathML/CDATA, URL/srcset/data-image filtering, base URL handling, iframe policy metadata, form/embed/noscript/template fallback, table foster parenting, XML namespaces, obsolete media URLs, picture-source pruning, select/optgroup label fallback, meta refresh/title/named metadata/property/social image/crawler/CSP/referrer metadata, passive link relations, navigation side-effect filtering, image maps, hidden/inert states, details/dialog review metadata, microdata/RDFa metadata, or source-line diagnostics. This slice only owns popover state and popover invoker attribute normalization.
