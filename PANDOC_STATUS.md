# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 3,867 | 1,096 |
| JSON / native AST | 87 | 252 |
| Typst input | 0 | 17 |
| PPTX / XLSX | 0 | 2 |
| Wiki / roff / text markup readers | 0 | 20 |
| HTML / XML / JATS / BITS DOM | 29 | 54 |
| DOCX / OpenXML | 95 | 256 |
| EPUB / EPUB3 | 24 | 15 |
| CSV / TSV | 2 | 4 |
| CSL / BibTeX / BibLaTeX / csljson / RIS / EndNote XML | 80 | 227 |
| LaTeX / TeX / math | 21 | 36 |
| DocBook | 16 | 17 |
| RTF | 4 | 27 |
| Shared ZIP / OPC package | 107 | 578 |
| PDF import (adjacent; not upstream Pandoc input) | 51 | N/A - Pandoc output/engine boundary only |
| Legacy DOC / CFB (adjacent; not a current upstream Pandoc input token) | 7 | N/A - not a current upstream Pandoc input token |

Latest Markdown/CommonMark/GFM evidence: bundled Markdown reader remainder coverage preserves list/setext, list padding/marker provenance, lazy block quote termination, inline/link/entity/reference normalization, metadata/raw/native-span enablement, table/figure/caption source metadata, implicit figures, and wrapped figure captions.

Current Pandoc counters: 8,146 PHP passes / 0 failures and 7,966 mapped upstream cases. Markdown reader remainder bundle adds 1,210 mapped Markdown/CommonMark/GFM cases across 22 bundled singleton candidates on top of current main plus the writer definition-body slice; post-rebase validation passed with `jq empty`, exact conflict-marker scan, `git diff --check`, `php -l` for touched PHP files, focused bundled Markdown reader/writer gate (`18` files, `21,745` assertions, `0` failures), and full `lanes/pandoc/tests` (`103` files, `125,248` assertions, `0` failures).
