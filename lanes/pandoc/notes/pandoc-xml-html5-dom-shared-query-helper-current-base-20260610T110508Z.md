# Pandoc XML/HTML5 DOM Core Current Base - Shared XML Query Helper

Slice: `pandoc-xml-html5-dom-shared-query-helper-current-base-20260610T110508Z`

## Scope

This core-blocker slice adds reusable namespace-aware XML DOM query primitives
to `XmlHtmlDom`:

- `rootElement()`
- `elementMatches()`
- `childElements()` / `firstChildElement()`
- `descendantElements()` / `firstDescendantElement()`
- `attribute()`

The helpers are aimed at DOCX/OpenXML, EPUB3, ODF/ODT, and OPC package readers
that repeatedly need safe root validation, namespace filtering, descendant
lookup, and namespaced attribute extraction after `XmlHtmlDom::loadXmlDocument()`.
As a concrete consumer, OPC content-types and relationships XML root validation
now use `XmlHtmlDom::rootElement()`.

## Non-Overlap

This does not add Markdown/plain/CommonMark/wiki/roff/media-bag diagnostic work
and does not extend HTML microdata or fragment sanitizer metadata. It is limited
to shared XML/HTML DOM package-reader primitives and an OPC parser call-site
using the shared matcher.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/OpcContentTypes.php`
- `php -l lanes/pandoc/src/OpcRelationships.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 239 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3718 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `4 test files, 4210 assertions, 0 failures`

## Accounting

- `phpPass`: `2952 -> 2953`
- `benchmarkDenominator.mapped`: `3124 -> 3125`
- `xmlHtmlDomCoreCases`: `8 -> 9`
- `mappedXmlHtmlDomCoreCases`: `9 -> 10`
- `xmlHtmlDomCoreAssertions`: `130 -> 147`
- Added `mappedXmlHtmlDomSharedQueryCases: 1`
- Added `xmlHtmlDomSharedQueryAssertions: 17`

## Dependency Closure

No Pandoc, Cabal/Haskell runner, office suite, zip/unzip, browser renderer,
external XML/HTML validator, online sanitizer, online service, live provider
test, or live-service provider test was executed. The slice uses the existing
native PHP `DOMDocument`/libxml safe XML loader and focused PHP tests only.
