# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 73 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 452 | 1,096 |
| JSON / native AST | 62 | 252 |
| Typst input | 0 | 17 |
| PPTX / XLSX | 0 | 2 |
| Wiki / roff / text markup readers | 0 | 20 |
| HTML / XML / JATS / BITS DOM | 29 | 54 |
| DOCX / OpenXML | 95 | 256 |
| EPUB / EPUB3 | 10 | 15 |
| CSV / TSV | 2 | 4 |
| CSL / BibTeX / BibLaTeX / csljson / RIS / EndNote XML | 79 | 227 |
| LaTeX / TeX / math | 21 | 36 |
| DocBook | 16 | 17 |
| RTF | 4 | 27 |
| Shared ZIP / OPC package | 107 | 578 |
| PDF import (adjacent; not upstream Pandoc input) | 51 | N/A - Pandoc output/engine boundary only |
| Legacy DOC / CFB (adjacent; not upstream Pandoc input) | 7 | N/A - not a current upstream Pandoc input token |

Latest ODF/ODT evidence: `OdfReader` and `OpenDocumentPackage` summarize manifest `preferred-view-mode` review metadata, including root applicability, defined OASIS modes, vendor namespaced tokens, invalid unqualified tokens, non-root diagnostics, and rich/compact package provenance parity.

Current Pandoc counters: 3,620 PHP passes / 0 failures and 3,628 mapped upstream cases. Verification passed `php -l` for `OdfReader.php`, `OpenDocumentPackage.php`, and `OdfReaderTest.php`; focused ODF/ODT gate (`5` files, `6,550` assertions, `0` failures); full `lanes/pandoc/tests` (`46` files, `84,891` assertions, `0` failures); `jq empty`; and `git diff --check`.
