# XML/HTML5 DOM break-element reviewer summaries

Slice: XML/HTML5 DOM break and separator element reviewer handoff on accepted
base `b6fb8d15a`.

## Behavior

- `XmlHtmlDom::summarizeHtmlFragment()` now classifies HTML `br`, `wbr`, and
  `hr` nodes with bounded reviewer metadata.
- `br` reports `breakElement=line-break`, `breakTag=br`,
  `textEquivalent="\n"`, and `hardBreak=true`.
- `wbr` reports `breakElement=word-break-opportunity`, `breakTag=wbr`,
  `textEquivalent=""`, and `softBreakOpportunity=true`.
- `hr` reports `breakElement=thematic-break`, `breakTag=hr`, and
  `blockSeparator=true`.
- Existing global attribute summaries still apply to these elements, so ids,
  classes, and dataset state remain available beside the break metadata.
- Serialization is unchanged: recovered HTML5 void elements still round-trip as
  deterministic raw HTML, and WordPress handoff keeps the raw `wbr`/`hr` markup.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 733 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65836 assertions, 0 failures`

## Counters

- `phpPass`: `3112 -> 3113`
- `mappedXmlHtmlDomBreakElementCases`: `0 -> 1`
- `xmlHtmlDomBreakElementAssertions`: `0 -> 21`
- `UPSTREAM_TEST_MANIFEST upstream.mapped`: `3207 -> 3208`

## Support Boundary

No new support component is needed. This reuses the native PHP HTML5 fragment
loader, deterministic DOM serializer, summary walker, `AstNode` raw-HTML
handoff, and `WordPressBlockWriter`. No Pandoc, Cabal/Haskell runner, browser
renderer, external sanitizer, external validator, online service, live provider
test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for text-level semantic
elements, lists, quote/cite attribution, revision datetime metadata,
time/data/meter/progress values, forms, media resources, hyperlinks, figures,
tables, SVG/MathML casing, raw text/RCDATA, ruby annotations, declarative
shadow-root/slot metadata, microdata/RDFa, or sanitizer source-line diagnostics.
This slice owns only reviewer classification of the already parsed `br`, `wbr`,
and `hr` break/separator elements.
