# Pandoc Status

Updated: 2026-06-14 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 53 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

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
| PDF import (adjacent; not upstream Pandoc input) | 49 | N/A - Pandoc output/engine boundary only |
| Legacy DOC / CFB (adjacent; not upstream Pandoc input) | 7 | N/A - not a current upstream Pandoc input token |

Latest registry evidence: `PandocFormatRegistry` records bounded wiki-family alias collision diagnostics for the `wiki` token suffix and `.wiki` MediaWiki/Vimwiki fixture-extension conflict while keeping `.wiki => mediawiki` extension inference unchanged. The slice keeps stable unsupported reader/writer reason payloads, empty native implementation records, `externalToolFree=true`, `directReaderParitySupported=false`, and `directWriterParitySupported=false`; native wiki reader/writer parity remains unsupported.

Current Pandoc counters: 3,518 PHP passes / 0 failures and 3,437 mapped upstream cases. Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`, focused `PandocFormatRegistryTest.php` (`1` file, `2,484` assertions, `0` failures), full `lanes/pandoc/tests` (`46` files, `83,323` assertions, `0` failures), `jq empty`, and `git diff --check`.
