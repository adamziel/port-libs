# Pandoc XML/HTML5 DOM Core Current Base - Named Character References

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T091158Z`

Base accepted HEAD: `e6968ed818a69e9dc12dd229c89caaf4bc025eb5`

## Implementation

- `XmlHtmlDom::protectHtmlRcdataElements()` now normalizes a bounded set of
  HTML5 named character references before libxml parsing for ordinary HTML
  fragment/document content and RCDATA `title`/`textarea` bodies.
- The bounded set covers `&NoBreak;`, `&NewLine;`, `&Tab;`, `&hopf;`,
  semicolonless `&nbsp`, and semicolonless `&copy`.
- Semicolonless decoding is intentionally limited to legacy `nbsp` and `copy`
  names; newer names such as `&NoBreak` remain literal unless the semicolon is
  present.
- Raw-text bodies such as `script`, `style`, `xmp`, `noembed`, `noframes`, and
  `plaintext` stay literal rather than entity-decoded.
- The WordPress XML/HTML5 DOM handoff smoke now includes a character-reference
  review packet and asserts that decoded references reach the raw HTML block
  handoff without ampersand fallback.

## Source Truth

Source truth is the lane-local manifest mapping for Pandoc upstream
`test/html-reader.html` Special Characters coverage and the existing native
HTML reader / XML-HTML5 DOM support contract. The local `.upstream-cache` does
not contain a hydrated Pandoc checkout for this worktree, so no upstream runner
or fixture command was executed.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
external XML/HTML tool, online sanitizer, online service, live provider test,
or live-service provider test was executed.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this
  lane before editing.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  passed with `1 test files, 130 assertions, 0 failures`.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1225 assertions, 0 failures`.
- Pre-implementation probe:
  `Html5Dom`/`Html5DomFragment` serialized `&NoBreak;`, `&NewLine;`, `&Tab;`,
  and `&hopf;` as literal escaped ampersand fallback.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  passed with `1 test files, 143 assertions, 0 failures`.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 1243 assertions, 0 failures`.
- DOM family green:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `3 test files, 1544 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  passed with `xml/html5 dom handoff self-test ok`.

## Mapping Delta

- `benchmarkDenominator.mapped`: `2009` -> `2010`.
- `phpPass`: `1589` -> `1591`.
- `xmlHtmlDomCoreCases`: `8` -> `9`.
- `mappedXmlHtmlDomCoreCases`: `8` -> `9`.
- `xmlHtmlDomCoreAssertions`: `124` -> `155`.
- Added `mappedXmlHtmlDomNamedCharacterReferenceCases: 1`.
- Focused DOM-family assertions: `1513` -> `1544`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/Html5DomTest.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `git diff --check -- lanes/pandoc`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `XmlHtmlDom`,
`Html5Dom`, `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, DOM/libxml
`NONET` parser paths, focused DOM tests, and the lane-local WordPress
XML/HTML5 DOM handoff example.

## Non-Overlap

This slice does not repeat DTD/entity rejection, processing-instruction
filtering, XML declaration preflight, comment-boundary serialization, raw text/
RCDATA/plaintext escaping policy, SVG/MathML foreign-content casing,
foreign-content CDATA normalization, URL/srcset filtering, data-image handling,
base URL resolution, inactive fallback base isolation, SVG resource filtering,
form/embed/object/applet/noscript/template fallback unwrapping, iframe srcdoc
handoff, safe iframe source links, iframe policy metadata, table
foster-parenting, XML namespace serialization, obsolete media URL attributes,
picture-source pruning, input/select label preservation, media track metadata,
meta refresh filtering, passive named/property meta handoff, passive link
relation handoff, navigation side-effect stripping, image map links, details/
dialog/hidden/inert/popover review metadata, semantic microdata/RDFa review
metadata, time datetime metadata, revision metadata, editing-state metadata,
or source-line diagnostics.

It owns only bounded extra HTML5 named character reference normalization before
native DOM reader and WordPress raw HTML handoff.

## Follow-Up

Keep broader WHATWG named-reference tables, browser tree-builder parity,
`q`/`blockquote` cite provenance, XHTML-to-AST conversion, browser sanitizer
parity, CSS/media execution, and upstream Haskell runner dependency closure as
separate bounded slices.
