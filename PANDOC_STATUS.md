# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,717 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` preserves attributed table and figure caption source metadata across Markdown caption lines, table review packets, Markdown writeback, WordPress handoff, and automatic HTML table caption output; existing native divs, URL normalization, explicit figure captions, emoji aliases, inline/link/escape output, numbered-example references, block/list/code marker output, and writer table fallback coverage remain covered.

Current Pandoc counters: 6,575 PHP passes / 0 failures and 6,465 mapped upstream cases. Markdown reader caption source completion validation passed after rebase onto current main `666f6aae2d`: `php -l` for `MarkdownReader.php`, `MarkdownReaderCaptionSourceCompletionTest.php`, and `MarkdownReaderBlocksSurgeTest.php`; focused `MarkdownReaderCaptionSourceCompletionTest.php` (`1` file, `1,897` assertions, `0` failures); focused `MarkdownReaderBlocksSurgeTest.php` plus `MarkdownReaderCaptionSourceCompletionTest.php` (`2` files, `5,252` assertions, `0` failures); full `lanes/pandoc/tests` (`79` files, `108,577` assertions, `0` failures); JSON validation; `git diff --check`; and exact conflict-marker scan.
