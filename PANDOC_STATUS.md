# Pandoc Status

Updated: 2026-06-16 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 87 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

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
| Shared ZIP / OPC package | 110 | 578 |
| PDF import (adjacent; not upstream Pandoc input) | 51 | N/A - Pandoc output/engine boundary only |
| Legacy DOC / CFB (adjacent; not a current upstream Pandoc input token) | 7 | N/A - not a current upstream Pandoc input token |

Latest package/core-format evidence: bundled ZIP/OPC and ODF/ODT package coverage preserves ZIP package comment layout, ZIP manifest preflight, selected ZIP zero-byte handoff buckets, ODF package comment provenance, ODT encrypted image manifest provenance, ODT ZIP timestamp provenance, ODF package identity preflight, and ODT layout-cache sidecar metadata-only review policy.

Current Pandoc counters: 15,351 PHP passes / 0 failures and 15,007 mapped upstream cases. Package/core-format bundle adds seven mapped non-Markdown cases across ready singleton MRs `plib-wisp-w6d8`, `plib-wisp-8nf3`, `plib-wisp-u2pw`, `plib-wisp-9zk8`, `plib-wisp-71ko`, `plib-wisp-8le7`, and `plib-wisp-st2l`; validation passed with `php -l` for touched PHP files, focused package gate (`4` files, `11,618` assertions, `0` failures), ODF/ODT readiness sentinel (`1` file, `24` assertions, `0` failures), full `lanes/pandoc/tests` (`181` files, `166,256` assertions, `0` failures), `jq empty`, `git diff --check`, and exact conflict-marker scan.
