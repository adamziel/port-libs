# Pandoc XML/HTML5 DOM Core Current Base - Extended Spacing References

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T235007Z`

Base accepted HEAD: `e0b67d45019623ea8bcde064daa47ff3c086103d`

## Implementation

- Extended `XmlHtmlDom`'s bounded HTML5 named character reference pre-normalizer with WHATWG-listed spacing aliases that libxml leaves as literal ampersand text in this environment:
  - `NonBreakingSpace` -> U+00A0
  - `ThinSpace` / `thinsp` -> U+2009
  - `ThickSpace` -> U+205F U+200A
  - `VeryThinSpace` / `hairsp` -> U+200A
  - `NegativeVeryThinSpace`, `NegativeMediumSpace`, and `NegativeThickSpace` -> U+200B
- Covered the low-level `XmlHtmlDom` fragment loader, `Html5Dom` facade, sanitized `Html5DomFragment` handoff, and the WordPress raw HTML example.

## Source Truth

Source truth is the HTML Standard named-character reference table (`https://html.spec.whatwg.org/dev/named-characters.html`), which lists those spacing names and code points, plus the existing lane contract that Pandoc-style HTML imports should decode bounded HTML5 references before deterministic native PHP raw HTML handoff.

No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML tool, online service, live provider test, or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present before editing.
- Current-behavior probe before edits showed `NonBreakingSpace` and `ThinSpace` still serialized as `&amp;...` fallbacks in `XmlHtmlDom`/`Html5Dom`.
- Red-first focused DOM family:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 1981 assertions, 3 failures`
- Final focused DOM family:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 2031 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`

## Mapping Delta

- `lane-status.json` `phpPass`: `1984 -> 1985`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2402 -> 2403`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 195` (`+71` focused DOM family assertions).
- Added `mappedXmlHtmlDomSpacingReferenceCases: 1`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/tests/Html5DomTest.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `XmlHtmlDom`, `Html5Dom`, `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET` parsing, focused DOM tests, and the existing WordPress XML/HTML5 DOM handoff example.

## Non-Overlap

This does not repeat DTD/entity rejection, processing-instruction filtering, XML declaration preflight, raw text/RCDATA/plaintext handling, SVG/MathML foreign-content casing, foreign-content CDATA normalization, URL/srcset/data-image/base URL handling, forms/embed/noscript/template/iframe handling, table foster parenting, namespace serialization, media/meta/link handoff, image maps, details/dialog/hidden/inert/popover handling, semantic microdata/RDFa, language/direction, revision/time/editing metadata, source-line diagnostics, or the earlier bounded named-reference rows for `NoBreak`, `NewLine`, `Tab`, `hopf`, `ApplyFunction`, `InvisibleTimes`, `InvisibleComma`, `MediumSpace`, `ZeroWidthSpace`, and `NegativeThinSpace`.

## Follow-Up

Keep full HTML5 entity-table parity, richer HTML tree-builder repair, parser-to-AST handoff, browser sanitizer parity, and upstream Haskell runner parity as separate bounded slices.
