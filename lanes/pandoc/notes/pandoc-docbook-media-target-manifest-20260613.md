# Pandoc DocBook media target manifest diagnostics

Date: 2026-06-13
Base: `dc8677bb`
Bead: `plib-tz3kw`

## Verdict

DocBook remains partial, not ship-ready. This slice closes one bounded media
diagnostic gap without claiming direct reader parity.

## Implemented slice

`XmlHtmlDom::summarizeDocBookStructure()` now emits a review-only
`mediaTargetManifest` packet for DocBook `mediaobject` and
`inlinemediaobject` nodes. The packet records:

- media target rows with element IDs, target attribute source, basename,
  content type/source, inline state, textobject alternatives, and linkend/id
  reference state
- repeated target summaries, basename buckets, and content-type buckets
- diagnostics for missing targets, missing content-type metadata,
  imageobject/textobject association gaps, inline media without alt text, and
  repeated targets

The packet keeps `directReaderParity=false` and does not invoke Pandoc, XML
validators, browsers, Node tooling, online services, live providers, or
external validators.

## Counters

- PHP pass numerator: 3,350 -> 3,351
- PHP fail: 0
- Mapped upstream cases: 3,310 -> 3,311
- New mapped counter: `mappedXmlHtmlDomDocBookMediaTargetManifestCases = 1`
- New assertion counter: `xmlHtmlDomDocBookMediaTargetManifestAssertions = 49`
- DocBook local evidence: 17 -> 18 against the existing DocBook/table geometry
  denominator

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 file, 1,979 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 45 files, 75,490 assertions, 0 failures

## Remaining DocBook gaps

Full DocBook reader parity still needs body conversion into shared AST
blocks/inlines, broader section nesting semantics, references and bibliography
mapping, generated AST round trips, non-table figure/media conversion, admonition
block conversion, and broader upstream fixture hydration.
