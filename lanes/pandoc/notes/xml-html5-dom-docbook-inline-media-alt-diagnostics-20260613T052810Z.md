# XML/HTML5 DOM DocBook Inline Media Alt Diagnostics

## Scope

`XmlHtmlDom` now adds bounded DocBook media review metadata for
`mediaobject`, `inlinemediaobject`, `imageobject`, `imagedata`, `textobject`,
and `alt` elements without invoking Pandoc, XML validators, browsers, Node
tooling, online services, live providers, or external validators.

The focused slice preserves:

- alt and textobject fallback evidence for media review packets
- imagedata target, target path, basename, extension, and content-type summaries
- missing alt diagnostics for image-bearing media objects without accessible text
- linkend/id association summaries with missing and invalid target diagnostics

## Counters

- `phpPass`: `3397 -> 3398`
- `phpFail`: `0`
- `mappedXmlHtmlDomDocBookInlineMediaAltCases`: `1`
- `xmlHtmlDomDocBookInlineMediaAltAssertions`: `45`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 2959 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 78026 assertions, 0 failures`
