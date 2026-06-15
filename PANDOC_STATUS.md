# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,617 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` preserves attributed native HTML div blocks, URL control-byte normalization, explicit figure captions, and existing emoji alias coverage; `MarkdownWriter` preserves compact Pandoc `Plain` list items, task-list continuation indentation, hardened inline/link/escape output, numbered-example references, validated block/list/code marker output, and automatic HTML table fallback coverage.

Current Pandoc counters: 6,475 PHP passes / 0 failures and 6,465 mapped upstream cases. Markdown writer Plain list-item validation passed after rebase onto current main `87b401356f`: `php -l` for `MarkdownWriter.php` and `MarkdownWriterPlainListItemSurgeTest.php`; focused `MarkdownWriterPlainListItemSurgeTest.php` (`1` file, `358` assertions, `0` failures); adjacent Markdown block/list/code regression passed (`5` files, `4,537` assertions, `0` failures); `MarkdownReaderTest.php` fixture regression passed (`1` file, `7,019` assertions, `0` failures); full `lanes/pandoc/tests` passed (`77` files, `106,802` assertions, `0` failures); `jq empty`; `git diff --check`; and exact conflict-marker scan.
