# Pandoc DocBook Bibliography Entry Metadata

## Scope

`XmlHtmlDom` now exposes bounded DocBook bibliography entry metadata for
`biblioentry` and `bibliomixed` review packets without invoking Pandoc, XML
validators, browser renderers, Node tooling, online services, live provider
tests, or external validators.

The focused slice preserves:

- title, author, editor, year/date, publisher, `id`, and `xml:id` provenance
- `xref` and `linkend` target summaries for bibliography references
- missing and duplicate metadata diagnostics for review-only handoff packets
- unsupported bibliography child summaries without claiming direct reader parity

## Counters

- `phpPass`: `3403 -> 3404`
- `phpFail`: `0`
- `mappedXmlHtmlDomDocBookBibliographyEntryMetadataCases`: `1`
- `xmlHtmlDomDocBookBibliographyEntryMetadataAssertions`: `77`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test file, 2992 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 78351 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/notes/pandoc-docbook-bibliography-entry-metadata-20260613T054432Z.md`
