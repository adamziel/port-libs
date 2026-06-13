# XML/HTML/JATS Body Diagnostics

Slice: `pandoc-xml-html-jats-body-diagnostics`
Issue: `plib-t44tm`
Rebased base: `dc8677bb`

## Verdict

Not shippable yet.

The native PHP lane now has `278 / 29` local XML/HTML/JATS/DocBook DOM evidence cases against the accepted upstream XML/HTML/JATS/DocBook format-related denominator, or `958.6%` as an evidence ratio. This remains evidence accounting only; it is not upstream runner parity.

`html` remains partial. `xml`, `jats`, and `bits` remain unsupported as full direct Pandoc input readers in the registry.

## Implemented Gap

`XmlHtmlDom::summarizeJatsFrontMatter()` now preserves bounded JATS/BITS body diagnostics while keeping `directReaderParity=false`.

The review packet now reports body root selection, section hierarchy/depth/type metadata, direct and descendant section paragraph counts, resolved and unresolved xref targets, reference labels and back-reference counts, figure labels/captions/graphic hrefs, table-wrap labels/captions/row counts, unreferenced figure/table buckets, and BITS book-part body metadata.

## Remaining Gaps

- Implement full Pandoc `xml` input reader mapping into the shared AST.
- Implement full JATS/BITS body, back matter, table, figure, reference, and citation-reader parity.
- Complete HTML5 tree-construction parity and upstream runner comparison.
- Finish DocBook XML reader parity beyond table geometry.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 file, 1970 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 45 files, 75481 assertions, 0 failures

No Pandoc binary, browser renderer, external XML validator, online service, live provider test, or live-service provider test was invoked.
