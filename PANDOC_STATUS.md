# Pandoc Status

Updated: 2026-06-21 UTC

Current registry audit: 51 upstream input formats tracked; 29 have partial native PHP readers; 22 remain unsupported. Project-local inputs `pdf` and `doc` are tracked separately from upstream Pandoc inputs. The latest format-dispatch work keeps bounded `xml`, `jats`, and `bits` readers on `PortLibs\Pandoc\XmlReader`, keeps `docbook` on direct `PortLibs\Pandoc\DocBookReader` dispatch, moves `html` onto dedicated `PortLibs\Pandoc\HtmlReader` dispatch while preserving the current HTML-capable reader bridge, and adds bounded `opml` dispatch through `PortLibs\Pandoc\OpmlReader`; full Pandoc parity remains open.

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 89 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 3,867 | 1,096 |
| JSON / native AST | 87 | 252 |
| Typst input | 0 | 17 |
| PPTX / XLSX | 0 | 2 |
| Wiki / roff / text markup readers | 0 | 20 |
| OPML | 2 | Source-mapped upstream reader behavior |
| HTML / XML / JATS / BITS DOM | 30 | 54 |
| DOCX / OpenXML | 95 | 256 |
| EPUB / EPUB3 | 24 | 15 |
| CSV / TSV | 2 | 4 |
| CSL / BibTeX / BibLaTeX / csljson / RIS / EndNote XML | 80 | 227 |
| LaTeX / TeX / math | 21 | 36 |
| DocBook | 28 | 17 |
| RTF | 4 | 27 |
| Shared ZIP / OPC package | 110 | 578 |
| PDF import (adjacent; not upstream Pandoc input) | 51 | N/A - Pandoc output/engine boundary only |
| Legacy DOC / CFB (adjacent; not a current upstream Pandoc input token) | 7 | N/A - not a current upstream Pandoc input token |

Latest package/core-format evidence: bundled ZIP/OPC and ODF/ODT package coverage preserves ODF manifest control-byte path preflight, ZIP package comment layout, ZIP manifest preflight, selected ZIP zero-byte handoff buckets, ODF package comment provenance, ODT encrypted image manifest provenance, ODT ZIP timestamp provenance, ODF package identity preflight, ODT layout-cache sidecar metadata-only review policy, and ODF package inventory role/byte-exposure byte buckets.

Current Pandoc counters: 15,351 PHP passes / 0 failures and 15,007 mapped upstream cases before the latest HTML and OPML dispatch slices. The latest HTML reader-dispatch slice adds `PortLibs\Pandoc\HtmlReader` as the registered `html` input implementation, stamps HTML provenance metadata (`sourceFormat`, `reader`, `readerScope`, delegate, and source hash), and preserves existing DOM/raw-HTML/native-div/table/link/list behavior through the current HTML-capable bridge. The latest OPML slice adds `PortLibs\Pandoc\OpmlReader` for document title, owner, modified date, nested outlines, link outlines, Markdown notes, and bounded HTML inline markup inside outline text. Verification: OPML registry/converter gate (`3` files, `226` assertions, `0` failures), HTML-heavy DOM/Markdown sweep (`4` files, `13,708` assertions, `0` failures), broad reader smoke with OPML (`22` files, `18,234` assertions, `0` failures), PDF/markerPDF guard (`2` files, `3,051` assertions, `0` failures), local problematic-PDF smoke `tables=10 geometry=10 rects=896 mode=geometry`, and exact-string guard for the problematic PDF path/content terms (`0` hits).
