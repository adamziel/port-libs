# Pandoc XML/HTML5 DOM Core Current Base - Time Datetime Metadata

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T071127Z`

Base accepted HEAD: `44abaf24a25076821970566dd44baf5e3ae3c0b9`

## Implementation

- `Html5DomFragment` now converts valid HTML `<time datetime>` values into
  generated inert `data-pandoc-time-datetime` and `data-pandoc-time-kind`
  reviewer metadata before WordPress raw HTML handoff.
- The bounded grammar covers date, month, week, year, time, local datetime,
  global datetime with `Z` or numeric timezone offsets, and ISO duration
  values.
- Source `datetime` attributes are not serialized directly. Malformed values
  are stripped while preserving visible `<time>` text, and source-owned
  `data-pandoc-time-*` spoofing continues to be rejected by the existing
  reserved attribute filter.
- The WordPress HTML5 DOM fragment smoke now includes valid publication time,
  duration, and malformed legacy date cases.

## Source Truth

Source truth is the lane-local XML/HTML5 DOM support contract plus HTML5 raw
HTML review handoff policy: recovered fragments should preserve reviewer
visible content and useful provenance while avoiding active behavior and
source-owned reviewer metadata. Machine-readable time/date provenance is useful
for WordPress import review, but source `datetime` values should be bounded and
canonicalized before becoming generated Pandoc review metadata.

This is bounded native PHP sanitizer/serializer behavior. It is not full HTML5
tree-builder parity, browser sanitizer parity, microformat parsing, calendar
math beyond validation, XHTML-to-AST conversion, or upstream Pandoc runner
parity.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1164 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  failed with `1 test files, 1165 assertions, 1 failures` because raw
  `datetime` attributes, including malformed values, were still serialized.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1187 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1475 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `1978` -> `1979`.
- `phpPass`: `1557` -> `1558`.
- `xmlHtmlDomCoreCases`: `8` -> `9`.
- `mappedXmlHtmlDomCoreCases`: `8` -> `9`.
- `xmlHtmlDomCoreAssertions`: `124` -> `147`.
- Added `mappedXmlHtmlDomTimeDatetimeCases: 1`.
- Focused `Html5DomFragmentTest.php`: `1164` -> `1187` assertions.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET`
parser paths, existing reserved `data-pandoc-*` source-attribute filtering, and
the focused lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction
filtering, XML declaration preflight, comment-boundary serialization, raw text/
RCDATA/plaintext handling, SVG/MathML foreign-content casing, foreign-content
CDATA normalization, URL/srcset filtering, data-image handling, base URL
resolution, inactive fallback base isolation, SVG resource filtering, SVG
presentation resource URL filtering, form/embed/object/applet/noscript/template
fallback unwrapping, `iframe srcdoc` handoff, safe iframe source links, iframe
policy metadata, table foster-parenting, XML namespace serialization, obsolete
media URL attributes, picture-source pruning, input/select label preservation,
media track metadata, meta refresh filtering, passive named/property meta
handoff, passive link relation handoff, navigation side-effect stripping, image
map links, reserved source-owned reviewer attribute filtering, details/dialog/
hidden/popover review metadata, or semantic microdata/RDFa review metadata.

It owns only bounded `<time datetime>` metadata normalization for sanitized
reviewer fragments.

## Follow-Up

Keep full microformat extraction, richer date calendar semantics, XHTML-to-AST
conversion, full HTML5 tree-builder parity, browser sanitizer parity, CSS/media
execution, and upstream Haskell runner dependency closure as separate bounded
slices.
