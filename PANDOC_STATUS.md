# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,377 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` maps supplemental pipe, simple, and grid table captions with role, ARIA, language, direction, title, data, class, and decoded entity attributes, while preserving formatted short captions, link-starting long captions, Markdown writer attribute round-trip, table geometry placement, and WordPress short-caption handoff.

Current Pandoc counters: 6,235 PHP passes / 0 failures and 6,225 mapped upstream cases. Verification passed after rebase onto current main `c12e4c4023`: `php -l` for `MarkdownReader.php` and `MarkdownReaderTablesSurgeTest.php`; focused `MarkdownReaderTablesSurgeTest.php` passed 1 file, 1372 assertions, 0 failures; focused `MarkdownReaderTest.php` plus `MarkdownReaderTablesSurgeTest.php` passed 2 files, 8391 assertions, 0 failures; full `lanes/pandoc/tests` passed 74 files, 106253 assertions, 0 failures; `jq empty`; `git diff --check`; and exact conflict-marker scan.
