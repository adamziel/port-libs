# Pandoc Status

Updated: 2026-06-21 UTC

Current registry audit: 51 upstream input formats tracked; 31 have partial native PHP readers; 20 remain unsupported. Output registry audit: 75 upstream output formats tracked; 15 have partial native PHP writers; 60 remain unsupported. Project-local inputs `pdf` and `doc` are tracked separately from upstream Pandoc inputs. The latest format-dispatch work keeps bounded `xml`, `jats`, and `bits` readers on `PortLibs\Pandoc\XmlReader`, keeps `docbook` on direct `PortLibs\Pandoc\DocBookReader` dispatch, moves `html` onto dedicated `PortLibs\Pandoc\HtmlReader` dispatch while preserving the current HTML-capable reader bridge, adds bounded `opml` dispatch through `PortLibs\Pandoc\OpmlReader` plus `PortLibs\Pandoc\OpmlWriter`, closes the pinned upstream CSV/TSV parser evidence through `PortLibs\Pandoc\DelimitedTextReader`, closes the pinned upstream XLSX reader fixture through `PortLibs\Pandoc\XlsxReader`, and closes the pinned upstream PPTX reader fixture through `PortLibs\Pandoc\PptxReader`; full Pandoc parity remains open for the larger unsupported/partial format surface.

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 89 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 3,867 | 1,096 |
| JSON / native AST | 87 | 252 |
| Typst input | 0 | 17 |
| PPTX / XLSX | 2 | 2 pinned PPTX/XLSX reader fixtures covered; PPTX writer corpus remains open in the output registry |
| Wiki / roff / text markup readers | 0 | 20 |
| OPML | 7 | 2 upstream old-suite tests; reader fixture canonical-native exact, writer fixture exact |
| HTML / XML / JATS / BITS DOM | 30 | 54 |
| DOCX / OpenXML | 95 | 256 |
| EPUB / EPUB3 | 24 | 15 |
| CSV / TSV | 4 | 4 pinned CSV reader/parser evidence; RST csv-table integration remains tracked with RST |
| CSL / BibTeX / BibLaTeX / csljson / RIS / EndNote XML | 80 | 227 |
| LaTeX / TeX / math | 21 | 36 |
| DocBook | 28 | 17 |
| RTF | 4 | 27 |
| Shared ZIP / OPC package | 110 | 578 |
| PDF import (adjacent; not upstream Pandoc input) | 51 | N/A - Pandoc output/engine boundary only |
| Legacy DOC / CFB (adjacent; not a current upstream Pandoc input token) | 7 | N/A - not a current upstream Pandoc input token |

Latest package/core-format evidence: bundled ZIP/OPC and ODF/ODT package coverage preserves ODF manifest control-byte path preflight, ZIP package comment layout, ZIP manifest preflight, selected ZIP zero-byte handoff buckets, ODF package comment provenance, ODT encrypted image manifest provenance, ODT ZIP timestamp provenance, ODF package identity preflight, ODT layout-cache sidecar metadata-only review policy, and ODF package inventory role/byte-exposure byte buckets.

Recent checked gates are slice-scoped rather than a full-suite completion claim. The latest HTML reader-dispatch slice adds `PortLibs\Pandoc\HtmlReader` as the registered `html` input implementation, stamps HTML provenance metadata (`sourceFormat`, `reader`, `readerScope`, delegate, and source hash), and preserves existing DOM/raw-HTML/native-div/table/link/list behavior through the current HTML-capable bridge. The latest OPML slices add `PortLibs\Pandoc\OpmlReader` for document title, owner, modified date, nested outlines, link outlines, Markdown notes, and bounded HTML inline markup inside outline text, including canonical native semantic parity for Pandoc's upstream `opml-reader.opml`/`opml-reader.native` fixture, plus `PortLibs\Pandoc\OpmlWriter` for nested outline output, escaped HTML heading text, Markdown `_note` bodies, default OPML metadata, and Pandoc-compatible OPML note markers for tight lists, definition lists, horizontal rules, empty Div attributes, raw TeX inline nodes, empty links, link titles with quotes, OPML note wrapping, display-math softbreaks, footnote definition wrapping, and nested empty-attribute Div fence width. Direct pinned `testsuite.native` to `writer.opml` comparison with `columns => 80` is byte-for-byte equal: `actual_bytes=14075`, `expected_bytes=14075`, `same=yes`, Levenshtein `0`. The latest CSV/TSV slice ports the pinned Pandoc `Text.Pandoc.CSV`/`Text.Pandoc.Readers.CSV` behavior for direct CSV tables and available parser options. The latest XLSX slice ports the pinned Pandoc `Text.Pandoc.Readers.Xlsx` behavior for workbook relationship discovery, workbook sheet order, shared strings, direct font bold/italic style indexes, dense sheet grids, first-row table heads, numeric cells rendered with decimal notation, empty cells, and trailing empty-row trimming. The latest PPTX slice ports the pinned Pandoc `Text.Pandoc.Readers.Pptx` reader behavior for presentation relationship discovery, slide order, title placeholders, text boxes, Wingdings/explicit bullet grouping, simple tables, image references, and SmartArt hierarchy. Verification: CSV/TSV focused gate (`1` file, `419` assertions, `0` failures), XLSX/registry focused gate (`4` files, `367` assertions, `0` failures), PPTX/registry focused gate (`5` files, `932` assertions, `0` failures), broad reader/writer smoke including PPTX/XLSX (`25` files, `18,446` assertions, `0` failures), PDF/markerPDF guard (`2` files, `3,051` assertions, `0` failures), local problematic-PDF smoke `reconstruction=geometry tables=10 geometry=10 cells=114 rects=896 gray_attrs=34 collapsed_guard=clear spaced_guard=hit`, and exact-string guard for the problematic PDF path/content terms (`0` hits).
