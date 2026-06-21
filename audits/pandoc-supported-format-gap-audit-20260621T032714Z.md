# Pandoc Supported Format Gap Audit - 2026-06-21

Scope: current `port-libs` Pandoc lane support registry after the bounded XLSX, PPTX, and Jira reader slices, plus adjacent local PDF and legacy DOC inputs. This is an evidence audit, not a completion claim for the whole Pandoc port.

## Registry Snapshot

- Upstream input formats tracked: 51 total, 32 with partial native PHP readers, 19 unsupported.
- Upstream output formats tracked: 75 total, 15 with partial native PHP writers, 60 unsupported.
- Project-local inputs outside upstream Pandoc input tokens: `pdf`, `doc`.
- Rich package inputs: 6 of 6 have direct bounded native support (`docx`, `epub`, `ipynb`, `odt`, `pptx`, `xlsx`).
- XLSX result: `PortLibs\Pandoc\XlsxReader` covers the pinned upstream `test/xlsx-reader/basic.xlsx` to `test/xlsx-reader/basic.native` reader fixture surface from Pandoc commit `912bfa5e`.
- PPTX result: `PortLibs\Pandoc\PptxReader` covers the pinned upstream `test/pptx-reader/basic.pptx` to `test/pptx-reader/basic.native` reader fixture surface from Pandoc commit `912bfa5e`. PPTX output remains unsupported.
- Jira result: `PortLibs\Pandoc\JiraReader` covers the pinned upstream `Tests.Readers.Jira` unit semantics for paragraphs, headings, lists, block quotes, tables, panels, inline styles, links, images, and entities. The larger `test/jira-reader.jira` to `test/jira-reader.native` fixture is parsed but not exact, so Jira remains partial.

## Gap Register

| Family | Missing work | Port source where available | Next action |
| --- | --- | --- | --- |
| ODF / ODT | Marked ship-ready for current mapped evidence, but richer style/layout/media package edges remain outside the current completion claim. | Pandoc ODT reader modules and existing ODF package fixtures. | Keep green as a guard; add only regression-driven edges unless upstream fixture deltas appear. |
| Markdown / CommonMark / GFM | Local coverage exceeds the upstream row, but extension matrix, variant flags, and exact constructor edges remain the risk. | Pandoc Markdown/CommonMark readers and extension option tests. | Continue option-cluster parity tests instead of more broad smoke. |
| JSON / native AST | Constructor completeness and malformed/native escape edge cases remain. | Pandoc JSON/native reader and writer constructors. | Keep closing constructor families until native round-trip exactness is complete. |
| Typst | Unsupported input surface, 17 upstream tests open. | Pandoc Typst reader. | Queue after bounded rich-package readers unless a user-facing Typst import need appears first. |
| PPTX / XLSX | PPTX and XLSX pinned reader fixtures are covered. PPTX writer corpus is larger and still unsupported. | Pandoc PPTX reader/writer modules; shared OOXML helpers already used by DOCX/XLSX. | Keep PPTX writer in the output backlog; do not count reader completion as writer parity. |
| Wiki / roff / text markup | Unsupported: `asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `mediawiki`, `man`, `mdoc`, `muse`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `typst`, `vimwiki`. Jira moved to partial unit semantics, with fixture parity still open. | Pandoc reader modules per format. | Batch by syntax family; start with FB2 or another small golden-fixture denominator if the objective is a complete next format. |
| OPML | Reader and writer pinned fixtures are exact, but registry remains partial for option and malformed input edges. | Pandoc OPML reader/writer fixtures and native writer tests. | Preserve exact fixture gate; add only edge cases that match upstream behavior. |
| HTML / XML / JATS / BITS | HTML5 tree construction and XML/JATS semantic depth are still partial. | Pandoc HTML/XML/JATS readers and writer tests. | Continue DOM fixture clusters and table/list/link edge parity. |
| DOCX / OpenXML | Reader is broad but not complete for full style/layout/media/object edge parity. | Pandoc DOCX reader and OOXML helpers. | Reuse shared OPC helpers and close fixture clusters by document part. |
| EPUB / EPUB3 | Package/spine/nav/media edge parity remains partial. | Pandoc EPUB reader. | Add package-manifest and navigation fixture clusters. |
| CSV / TSV | Direct CSV/TSV parser evidence is closed; RST csv-table remains with RST, not direct CSV. | Pandoc CSV parser and RST reader. | Treat further CSV-table work as part of the RST plan. |
| Bibliography formats | CSL/BibTeX/BibLaTeX/csljson/RIS/EndNote XML remain partial for full citation processor parity. | Pandoc citation/bibliography modules. | Port by parser family and citation constructor evidence. |
| LaTeX / TeX / math | Math/LaTeX reader and writer edges remain partial. | Pandoc TeX/LaTeX readers and writers. | Prioritize math constructor exactness and writer escaping. |
| DocBook | Current fixture parity is broad, but namespace/media/citation exactness remains partial. | Pandoc DocBook reader/writer modules. | Keep namespace and media regression gates active. |
| RTF | Control words, destinations, tables, images, and metadata are still shallow. | Pandoc RTF reader. | Port destination/control-word clusters before image/table edges. |
| Shared ZIP / OPC package | Core package helpers are active, but writer assembly and damaged-package edges remain. | Pandoc package helpers plus existing local ZIP/OPC tests. | Keep as shared infrastructure for DOCX, ODT, EPUB, PPTX, and XLSX. |
| PDF import | PDF is adjacent to Pandoc input support. Remaining gaps are multi-page table continuation, stronger grid inference, non-rectangular fills/clipping, tagged-vs-geometry reconciliation, scanned/OCR boundary, forms/XFA/signatures/attachments, and visual fidelity. | Not available as a Pandoc reader port; Pandoc's PDF surface is output/engine boundary. Local source is markerPDF. | Continue markerPDF geometry/table/fill reconstruction work and keep local problematic-PDF smoke as a transient guard. |
| Legacy DOC / CFB | Local legacy DOC input is partial and outside current upstream Pandoc input tokens. | Existing local CFB/DOC parser evidence. | Expand binary Word edge cases only after upstream-token readers. |

## Porting Plan

1. Finish bounded upstream fixture denominators first. XLSX and PPTX reader fixtures are now covered; Jira reader unit semantics are covered but fixture parity remains open. The next small-denominator input should be chosen from FB2 or one of the text-markup readers, while PPTX writer remains an output-format project.
2. Keep each format registry entry partial until repo passing tests cover the upstream denominator and the implementation surface is not just fixture-shaped.
3. For high-surface text formats, port from Pandoc module clusters by syntax feature and fixture group, then add native/HTML/Markdown writer checks where the shared AST can prove constructor exactness.
4. For rich packages, reuse `ZipPackage`, `OpcRelationships`, `OpcPackagePath`, `XmlHtmlDom`, and existing DOCX/ODT/EPUB/XLSX package review patterns rather than adding separate ad hoc XML loaders.
5. For PDF, do not wait on Pandoc reader code that does not exist for this direction. Continue local markerPDF reconstruction: table continuation, cell spacing, fill/background inference, tagged table precedence, supplied OCR boundary, and block writer fidelity.

## Verification Evidence

- XLSX focused gate: `4` files, `367` assertions, `0` failures.
- PPTX focused gate: `5` files, `932` assertions, `0` failures.
- Jira focused gate: `3` files, `279` assertions, `0` failures.
- Jira larger fixture smoke: parsed without crashing, `blocks=51`, `native_bytes=14556`, `expected_bytes=20887`, `same=no`.
- Broad reader/writer smoke including Jira/PPTX/XLSX: `26` files, `18,516` assertions, `0` failures.
- PDF/markerPDF guard: `2` files, `3,051` assertions, `0` failures.
- Local problematic-PDF transient smoke: `reconstruction=geometry tables=10 geometry=10 cells=114 rects=896 gray_attrs=34 collapsed_guard=clear spaced_guard=hit`.
- Hardcode guard: repository, Playground assets, and demo worktree scan for the local PDF path and pasted visible sample terms returned `0` hits.
