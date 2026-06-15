# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 2,097 | 1,096 |
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
| Legacy DOC / CFB (adjacent; not upstream Pandoc input) | 7 | N/A - not a current upstream Pandoc input token |

Latest Markdown/CommonMark/GFM evidence validated after rebase: `MarkdownReader` accepts spaced raw_attribute format specs such as `{ = html }` and `{ = latex }` for raw inline code spans and fenced raw blocks, preserving raw node format/text plus Markdown and WordPress handoff behavior.

Current Pandoc counters: 5,798 PHP passes / 0 failures and 5,788 mapped upstream cases. Verification passed: `php -l` for `MarkdownReader.php` and `MarkdownReaderRawAttributeSpacingExtensionSurgeTest.php`; dedicated `MarkdownReaderRawAttributeSpacingExtensionSurgeTest.php` passed 1 file, 582 assertions, 0 failures; focused `MarkdownReaderRawAttributeSpacingExtensionSurgeTest.php` plus `MarkdownReaderMetadataRawExtensionSurgeTest.php` plus `MarkdownReaderRawInlineSurgeTest.php` plus `MarkdownReaderTest.php` plus `MarkdownWriterBlocksSurgeTest.php` passed 5 files, 10165 assertions, 0 failures; full `lanes/pandoc/tests` passed 70 files, 101650 assertions, 0 failures; `jq empty`; `git diff --check`; and exact conflict-marker scan.
