# Pandoc Status

Updated: 2026-06-13 UTC

Scope: native PHP input/import paths. Output formats are not tracked here unless they are part of an import dependency.
Counts are upstream tests passing out of upstream tests total.

## Overall

| Counter | Count |
| --- | ---: |
| In-scope input formats | 50 |
| Ship-ready input formats | 1 |
| Partial input formats | 21 |
| Unsupported input formats | 28 |
| PHP test failures | 0 |

ODF/ODT is marked ship-ready: 52 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, 260.0%, with 0 critical ODF/ODT gaps.

## Input / Import Formats

| Format / path | State | Passed upstream | Upstream tests |
| --- | --- | ---: | ---: |
| ODF / ODT / OpenDocument (`odt`) | ship-ready | 20 | 20 |
| Markdown / CommonMark / GFM | partial | 452 | 1,096 |
| HTML / XML / JATS / BITS DOM | partial | 29 | 29 |
| JSON / native AST | partial | 61 | 252 |
| DOCX / OpenXML | partial | 35 | 35 |
| EPUB / EPUB3 | partial | 9 | 9 |
| CSV / TSV | partial | 2 | 2 |
| CSL / BibTeX / BibLaTeX / csljson / RIS / EndNote XML | partial | 8 | 8 |
| LaTeX / TeX / math | partial | 14 | 14 |
| DocBook | partial | 16 | 16 |
| RTF | partial | 3 | 3 |
| Shared ZIP / OPC package | dependency | 67 | 67 |
| Typst input | unsupported | 0 | 17 |
| PPTX / XLSX | unsupported | 0 | 2 |
| Wiki / roff / text markup readers | unsupported | 0 | 20 |

## Adjacent Import Targets

| Target | State | Passed upstream | Upstream tests |
| --- | --- | ---: | ---: |
| PDF import | adjacent | 17 | 17 |
| Legacy DOC / CFB | adjacent | 7 | 7 |
| IPYNB / notebook | skipped this phase | 0 | 0 |
