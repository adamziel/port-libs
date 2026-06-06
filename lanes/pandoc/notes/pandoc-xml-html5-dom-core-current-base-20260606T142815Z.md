# Pandoc XML/HTML5 DOM Core Current Base - Passive Meta Properties

Slice: `pandoc-xml-html5-dom-core-current-base-20260606T142815Z`

Base accepted HEAD: `064eddcbafd853b7c3b205d0660f5ea55fe616f8`

## Implementation

- `Html5DomFragment` now converts bounded passive HTML meta properties into
  inert reviewer-visible spans before WordPress raw HTML handoff.
- Supported property names are `og:title`, `og:description`,
  `article:published_time`, `article:modified_time`, `twitter:title`, and
  `twitter:description`.
- Unsupported or media/resource properties such as `og:image` and
  `twitter:image` remain stripped with the original blocked `meta` element.
- The WordPress HTML5 DOM fragment smoke now includes passive property
  metadata and confirms media-resource properties are not exposed.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the existing
DOM follow-up gate for OpenGraph/Twitter/meta property handoff: recovered HTML
fragments should not preserve active or invisible DOM metadata as executable
HTML, but bounded text metadata is useful reviewer provenance for WordPress
imports.

This is bounded native PHP support-library behavior for Pandoc-reader review
handoff. It is not full HTML5 tree-builder parity, browser sanitizer parity,
CSS/media loading, OpenGraph/Twitter media URL policy, XHTML-to-AST conversion,
or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 653 assertions, 0 failures`.
- Focused green after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed
  with `1 test files, 675 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1759` -> `1760`.
- `phpPass`: `1345` -> `1346`.
- `xmlHtmlDomCoreCases`: `5` -> `6`.
- `mappedXmlHtmlDomCoreCases`: `5` -> `6`.
- `xmlHtmlDomCoreAssertions`: `70` -> `92`.
- Focused `Html5DomFragmentTest.php`: `653` -> `675` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, existing metadata cleaning, and the focused PHP lane test
harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction or XML
declaration filtering, comment-boundary serialization, raw text/RCDATA/
plaintext handling, SVG/MathML foreign-content casing, foreign-content CDATA,
URL/srcset filtering, raster SVG data images, base URL resolution, inactive
fallback base isolation, SVG resource filtering, form/embed/noscript/template
unwrap, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, picture-source pruning, explicit input/select labels, meta
refresh filtering, passive link relation handoff, navigation side-effect
stripping, named meta description/author/keywords/generator handoff, or SVG
CSS resource escape handling.

It owns only bounded passive `meta property` text metadata handoff for
sanitized reviewer fragments.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS cascade and
media resource loading, OpenGraph/Twitter media URL policy, XHTML-to-AST
conversion, and full upstream-runner parity as separate bounded slices.
