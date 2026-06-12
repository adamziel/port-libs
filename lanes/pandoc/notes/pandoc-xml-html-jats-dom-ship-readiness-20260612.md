# XML/HTML5/JATS DOM Ship Readiness

Slice: `pandoc-xml-html-jats-front-matter-review`
Issue: `plib-iz1ev`
Rebased base: `d7a0bfb42d`

## Verdict

Not shippable yet.

The assigned format family has `275 / 29` local passing evidence cases against the upstream XML/HTML/JATS/DocBook format-related denominator, or `948.3%` as an evidence ratio. That percentage is above 100% because local PHP tests are more granular than the upstream case counter; it is not a claim of upstream runner parity.

`html` remains partial. `xml`, `jats`, and `bits` remain unsupported as full direct Pandoc input readers in `PandocFormatRegistry`.

## Gap / Ship Matrix

| Surface | Upstream format-related tests | Local passing evidence | Uncovered / critical gap |
| --- | ---: | ---: | --- |
| HTML5 DOM parser/writer boundary | included in 29 | broad HTML DOM, metadata, escaping, attribute, table, media, form, and WordPress handoff tests | Complete HTML5 tree-construction parity and compare against the upstream runner. |
| XML safe DOM primitives | included in 29 | safe XML loading, namespace queries, XML fragment serialization, declaration/DTD/entity/PI rejection | Implement direct Pandoc `xml` reader mapping into the shared AST. |
| JATS/BITS XML-ish inputs | included in 29 | bounded front-matter review packet added in this slice | Implement full JATS/BITS body, back matter, references, figures, tables, and citation-reader parity. |
| DocBook XML-ish tables | included in 29 | DocBook table geometry and WordPress handoff tests | Finish full DocBook reader parity beyond table geometry. |

## Implemented Gap

Added `XmlHtmlDom::summarizeJatsFrontMatter()`, a bounded native PHP review packet primitive for safe JATS `article` and BITS `book` / `book-part` XML documents.

The packet reports root attributes, document type, DTD version, language, metadata root, titles, identifiers, abstract text, keywords, contributor names and roles, publication dates, section summaries, xref targets, references, figures, table-wraps, book-part counts, and an explicit `directReaderParity=false` marker.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 file, 1828 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: 6 files, 6256 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 73941 assertions, 0 failures

No Pandoc binary, browser renderer, external XML validator, online service, live provider test, or live-service provider test was invoked.
