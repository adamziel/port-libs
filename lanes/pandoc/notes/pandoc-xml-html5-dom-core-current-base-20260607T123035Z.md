# Pandoc XML/HTML5 DOM Core Current Base - Document Language Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260607T123035Z`

Base accepted HEAD: `e57c0bcf9b6e3ffa5b25f24a078d7756e1f0a24a`

## Implementation

- `Html5DomFragment` now extracts bounded full-document `<html lang>` /
  `<html xml:lang>` and `<html dir>` metadata before fragment normalization.
- Valid language tags are normalized into stable BCP-style review values
  such as `pt-BR`; valid directions are limited to `ltr`, `rtl`, and `auto`.
- The active `html`/`body` wrapper remains stripped from sanitized raw HTML,
  while the language and direction survive as inert
  `data-pandoc-meta-name/source/content` spans for WordPress review packets.
- Malformed language or direction values are diagnostics-only and do not leak
  into serialized HTML.
- The WordPress HTML5 DOM fragment smoke now starts from a bounded
  full-document fragment and verifies visible `Language: en-US` and
  `Direction: ltr` reviewer metadata.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus the static
Pandoc HTML-reader inventory for full-document metadata handoff. Recovered
HTML fragments should not hand active document wrapper elements to WordPress
raw HTML blocks, but document language and direction are useful reviewer
provenance for multilingual imports.

This is bounded native PHP support-library behavior. It is not full HTML5
tree-builder parity, browser sanitizer parity, CSS/media loading,
XHTML-to-AST conversion, or upstream Pandoc runner parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 925 assertions, 0 failures`.
- Pre-edit exploratory check with a full-document
  `<html lang="ar-EG" dir="rtl">...` fragment emitted title/body content
  without any language or direction reviewer metadata.
- Focused green after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 947 assertions, 0 failures`.
- Coupled DOM family:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1220 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1918` -> `1919`.
- `phpPass`: `1498` -> `1499`.
- `xmlHtmlDomCoreCases`: `7` -> `8`.
- `mappedXmlHtmlDomCoreCases`: `7` -> `8`.
- `xmlHtmlDomCoreAssertions`: `103` -> `125`.
- Added `mappedHtmlDocumentLanguageDirectionCases: 1`.
- Added `htmlDocumentLanguageDirectionAssertions: 22`.
- Focused `Html5DomFragmentTest.php`: `925` -> `947` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`: no
  syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`: `1
  test files, 947 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`:
  `3 test files, 1220 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`:
  `html5 dom fragment handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, existing metadata cleaning, and the focused PHP lane test
harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML parser, external sanitizer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction or XML
declaration filtering, comment-boundary serialization, raw text/RCDATA/
plaintext handling, SVG/MathML foreign-content casing, foreign-content CDATA,
URL/srcset filtering, raster SVG data images, base URL resolution, inactive
fallback base isolation, SVG resource filtering, form/embed/noscript/template
unwrap, table foster-parenting, XML namespace serialization, obsolete media
URL attributes, picture-source pruning, explicit input/select labels, meta
refresh filtering, title metadata, passive named meta fields, passive
OpenGraph/Twitter properties, social image metadata, passive link relations,
navigation side-effect stripping, image-map area handoff, hidden/details
review metadata, iframe policy provenance, or SVG CSS resource escape
handling.

It owns only bounded full-document HTML language and direction metadata
handoff for sanitized reviewer fragments.

## Follow-Up

Keep source-position diagnostics, richer sanitizer policy, CSS cascade and
media resource loading, additional inert document metadata, XHTML-to-AST
conversion, and full upstream-runner parity as separate bounded slices.
