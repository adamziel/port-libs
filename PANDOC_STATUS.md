# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,497 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownWriter` keeps task-list continuation paragraphs aligned to the list marker width rather than the checkbox width, preserving the current inline/link/escape completion, figure-caption, native-div, URL-normalization, emoji, and numbered-example coverage already on main.

Current Pandoc counters: 6,355 PHP passes / 0 failures and 6,345 mapped upstream cases. Markdown writer task-list continuation validation passed after rebase onto current main `ff183ef1a3`: `php -l` clean for `MarkdownWriter.php` and `MarkdownWriterTaskListContinuationSurgeTest.php`; focused task-list plus adjacent writer block/list/code suites passed (`5` files, `1,214` assertions, `0` failures); full `lanes/pandoc/tests` passed (`76` files, `106,386` assertions, `0` failures); `jq empty`, `git diff --check`, and exact conflict-marker scan passed.
