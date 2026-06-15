# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,707 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` preserves attributed native HTML div blocks, URL control-byte normalization, explicit figure captions, and existing emoji alias coverage; `MarkdownWriter` preserves hardened inline/link/escape output, indented marker literal escaping, reference/footnote/abbreviation definition escaping, numbered-example references, validated block/list/code marker output, and automatic HTML table fallback coverage.

Current Pandoc counters: 6,566 PHP passes / 0 failures and 6,455 mapped upstream cases. Markdown writer inline/link/escape expansion validation passed after rebase onto current main `666f6aae2d`: `php -l` for `MarkdownWriter.php` and `MarkdownWriterInlineLinkEscapeExpansionSurgeTest.php`; focused `MarkdownWriterInlineLinkEscapeExpansionSurgeTest.php` (`1` file, `51` assertions, `0` failures); focused Markdown writer inline/link/escape group (`6` files, `604` assertions, `0` failures); affected table caption/span gate (`2` files, `142` assertions, `0` failures); full `lanes/pandoc/tests` (`79` files, `106,731` assertions, `0` failures); `jq empty`; `git diff --check`; and exact conflict-marker scan.
