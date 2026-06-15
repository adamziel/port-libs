# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,447 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` preserves attributed native HTML div blocks, URL control-byte normalization, explicit figure captions, and existing emoji alias coverage; `MarkdownWriter` preserves hardened inline/link/escape output for citation-looking literals, compact autolinks, and escaped inline attributes.

Current Pandoc counters: 6,305 PHP passes / 0 failures and 6,295 mapped upstream cases. Markdown writer inline/link/escape completion validation passed after rebase onto current main `3df651d289`: `php -l` clean for `MarkdownWriter.php` and `MarkdownWriterInlineLinkEscapeCompletionSurgeTest.php`; focused writer tests passed (`3` files, `240` assertions, `0` failures); ordered-marker regression gate passed (`3` files, `784` assertions, `0` failures); Markdown-focused suite passed (`18` files, `19,241` assertions, `0` failures); full `lanes/pandoc/tests` passed (`75` files, `106,135` assertions, `0` failures); `jq empty`, `git diff --check`, and exact conflict-marker scan passed.
