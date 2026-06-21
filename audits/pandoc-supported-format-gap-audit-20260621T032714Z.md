# Pandoc Supported Format Gap Audit - 2026-06-21

Scope: current `port-libs` Pandoc lane support registry after the bounded XLSX reader slice, plus adjacent local PDF and legacy DOC inputs. This is an evidence audit, not a completion claim for the whole Pandoc port.

## Registry Snapshot

- Upstream input formats tracked: 51 total, 30 with partial native PHP readers, 21 unsupported.
- Upstream output formats tracked: 75 total, 15 with partial native PHP writers, 60 unsupported.
- Project-local inputs outside upstream Pandoc input tokens: `pdf`, `doc`.
- Rich package inputs: 5 of 6 have direct bounded native support (`docx`, `epub`, `ipynb`, `odt`, `xlsx`); `pptx` remains the unsupported rich-package input.
- XLSX result: `PortLibs\Pandoc\XlsxReader` now covers the pinned upstream `test/xlsx-reader/basic.xlsx` to `test/xlsx-reader/basic.native` reader fixture surface from Pandoc commit `912bfa5e`.

## Gap Register

| Family | Missing work | Port source where available | Next action |
| --- | --- | --- | --- |
| ODF / ODT | Marked ship-ready for current mapped evidence, but richer style/layout/media package edges remain outside the current completion claim. | Pandoc ODT reader modules and existing ODF package fixtures. | Keep green as a guard; add only regression-driven edges unless upstream fixture deltas appear. |
| Markdown / CommonMark / GFM | Local coverage exceeds the upstream row, but extension matrix, variant flags, and exact constructor edges remain the risk. | Pandoc Markdown/CommonMark readers and extension option tests. | Continue option-cluster parity tests instead of more broad smoke. |
| JSON / native AST | Constructor completeness and malformed/native escape edge cases remain. | Pandoc JSON/native reader and writer constructors. | Keep closing constructor families until native round-trip exactness is complete. |
| Typst | Unsupported input surface, 17 upstream tests open. | Pandoc Typst reader. | Queue after bounded rich-package readers unless a user-facing Typst import need appears first. |
| PPTX / XLSX | XLSX pinned reader fixture is covered. PPTX reader fixture remains open; PPTX writer corpus is larger and still unsupported. | Pandoc PPTX reader/writer modules; shared OOXML helpers already used by DOCX/XLSX. | Pick PPTX reader as the next bounded OOXML target after this commit. |
| Wiki / roff / text markup | Unsupported: `asciidoc`, `creole`, `djot`, `dokuwiki`, `haddock`, `jira`, `mediawiki`, `man`, `mdoc`, `muse`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `vimwiki`. | Pandoc reader modules per format. | Batch by syntax family; start with the smallest fixture denominator when capacity returns to text formats. |
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

1. Finish bounded upstream fixture denominators first. XLSX is now covered; PPTX reader is the next OOXML package target because it has the remaining single reader fixture in the `PPTX / XLSX` row and can reuse the shared ZIP/OPC/XML helpers.
2. Keep each format registry entry partial until repo passing tests cover the upstream denominator and the implementation surface is not just fixture-shaped.
3. For high-surface text formats, port from Pandoc module clusters by syntax feature and fixture group, then add native/HTML/Markdown writer checks where the shared AST can prove constructor exactness.
4. For rich packages, reuse `ZipPackage`, `OpcRelationships`, `OpcPackagePath`, `XmlHtmlDom`, and existing DOCX/ODT/EPUB/XLSX package review patterns rather than adding separate ad hoc XML loaders.
5. For PDF, do not wait on Pandoc reader code that does not exist for this direction. Continue local markerPDF reconstruction: table continuation, cell spacing, fill/background inference, tagged table precedence, supplied OCR boundary, and block writer fidelity.

## Verification Evidence

- XLSX focused gate: `4` files, `367` assertions, `0` failures.
- Broad reader/writer smoke including XLSX: `24` files, `18,391` assertions, `0` failures.
- PDF/markerPDF guard: `2` files, `3,051` assertions, `0` failures.
- Local problematic-PDF transient smoke: geometry reconstruction, `10` detected tables, `34` emitted background styles, collapsed-word guard negative.
- Hardcode guard: repository, Playground assets, and demo worktree scan for the local PDF path and pasted visible sample terms returned `0` hits.
