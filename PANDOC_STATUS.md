# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,327 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` preserves attributed native HTML div blocks with ids, normalized classes, data/ARIA/lang/dir/title/role/translate/xml:lang attributes, quote-aware values, Markdown fenced-div output, WordPress block handoff, URL control-byte normalization, and existing emoji alias coverage.

Current Pandoc counters: 6,185 PHP passes / 0 failures and 6,175 mapped upstream cases. Verification passed after rebase onto current main `cc87fc6ba3`: `php -l` for `MarkdownReader.php` and `MarkdownReaderMetadataRawExtensionSurgeTest.php`; focused native-div coverage with `MarkdownReaderTest.php` passed 2 files, 10079 assertions, 0 failures; full `lanes/pandoc/tests` passed 74 files, 105433 assertions, 0 failures; `jq empty`; `git diff --check`; and exact conflict-marker scan.