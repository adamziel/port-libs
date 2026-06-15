# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,277 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` percent-encodes C0/DEL control bytes in decoded inline link, image, reference-link, and angle-autolink destinations while preserving titles, labels, entity/backslash decoding, native span extension coverage, existing emoji alias coverage, and WordPress handoff.

Current Pandoc counters: 6,135 PHP passes / 0 failures and 6,125 mapped upstream cases. Verification passed after rebase onto current main `4cb757a1e1`: `php -l` for `MarkdownReader.php` and `MarkdownReaderUrlNormalizationSurgeTest.php`; focused URL-normalization coverage passed 1 file, 250 assertions, 0 failures; focused related Markdown reader coverage passed 7 files, 10169 assertions, 0 failures; full `lanes/pandoc/tests` passed 74 files, 104521 assertions, 0 failures; `jq empty`; `git diff --check`; and exact conflict-marker scan.