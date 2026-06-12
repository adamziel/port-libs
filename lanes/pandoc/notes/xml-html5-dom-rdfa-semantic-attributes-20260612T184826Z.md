# XML/HTML5 DOM RDFa semantic attribute review provenance

Issue: plib-zc72x
Date: 2026-06-12T18:48:26Z

## Scope

This slice adds bounded native PHP summary metadata for RDFa-style semantic attributes in `XmlHtmlDom` reviewer handoff packets.

Covered fields:
- `vocab` and `prefix` mappings, including invalid mapping diagnostics.
- `typeof`, `property`, `rel`, and `rev` token inventories with unsafe token buckets.
- `about`, `resource`, `datatype`, `content`, and `inlist` state.
- Deterministic raw HTML serialization and WordPress raw block propagation.

The summary is only emitted when an RDFa-specific trigger attribute is present, so ordinary `rel` links and ordinary `content` metadata do not gain RDFa fields by themselves.

## Accounting

- `phpPass`: 3259 -> 3260
- `phpFail`: 0
- `mappedXmlHtmlDomRdfaSemanticAttributeCases`: 1
- `xmlHtmlDomRdfaSemanticAttributeAssertions`: 52

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 1689 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 5 test files, 4624 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 72882 assertions, 0 failures

No Pandoc, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
