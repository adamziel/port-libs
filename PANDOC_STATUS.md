# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 85 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 1,072 | 1,096 |
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

Latest Markdown/CommonMark/GFM evidence: `MarkdownReader` preserves recognized raw inline HTML comments, declarations, processing instructions, CDATA, and known HTML/SVG/MathML tags as `raw_html_inline` while retaining nested list-item block coverage and keeping unsafe unknown inline tags on escaped/text paths.

Current Pandoc counters: 4,497 PHP passes / 0 failures and 4,487 mapped upstream cases. Verification passed `php -l` for `MarkdownReader.php`, `MarkdownReaderRawInlineSurgeTest.php`, `MarkdownReaderInlineSurgeTest.php`, and `MarkdownReaderTest.php`; focused Markdown raw-inline coverage (`3` files, `7,613` assertions, `0` failures); full `lanes/pandoc/tests` (`56` files, `93,958` assertions, `0` failures); `jq empty`; `git diff --check`; and exact conflict-marker scan.
