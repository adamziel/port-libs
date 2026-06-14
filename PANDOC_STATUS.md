# Pandoc Status

Updated: 2026-06-14 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 54 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 452 | 1,096 |
| JSON / native AST | 62 | 252 |
| Typst input | 0 | 17 |
| PPTX / XLSX | 0 | 2 |
| Wiki / roff / text markup readers | 0 | 20 |
| HTML / XML / JATS / BITS DOM | 29 | 54 |
| DOCX / OpenXML | 95 | 256 |
| EPUB / EPUB3 | 10 | 15 |
| CSV / TSV | 2 | 4 |
| CSL / BibTeX / BibLaTeX / csljson / RIS / EndNote XML | 79 | 227 |
| LaTeX / TeX / math | 21 | 36 |
| DocBook | 16 | 17 |
| RTF | 4 | 27 |
| Shared ZIP / OPC package | 107 | 578 |
| PDF import (adjacent; not upstream Pandoc input) | 51 | N/A - Pandoc output/engine boundary only |
| Legacy DOC / CFB (adjacent; not upstream Pandoc input) | 7 | N/A - not a current upstream Pandoc input token |

Latest PDF/Typst evidence: `PdfEngineHandoff` parses explicit Typst `--deps-format=zero` sidecars as NUL-delimited input-only dependency provenance, including local inputs, Typst package review metadata, root read-boundary policy, and dependency-output review when the sidecar carries no output target.

Current Pandoc counters: 3,528 PHP passes / 0 failures and 3,445 mapped upstream cases. Verification passed `php -l lanes/pandoc/src/PdfEngineHandoff.php`, `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, focused `PdfEngineHandoffTest.php` (`1` file, `2,543` assertions, `0` failures), full `lanes/pandoc/tests` (`46` files, `83,483` assertions, `0` failures), `jq empty`, and `git diff --check`.
