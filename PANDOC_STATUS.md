# Pandoc Status

Updated: 2026-06-13 UTC after Pandoc wiki alias collision taxonomy on current main `68363c588e`.

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 52 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | ---: |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 452 | 1,096 |
| JSON / native AST | 61 | 252 |
| Typst input | 0 | 17 |
| PPTX / XLSX | 0 | 2 |
| Wiki / roff / text markup readers | 0 | 20 |
| HTML / XML / JATS / BITS DOM | 297 | audit needed |
| DOCX / OpenXML | 94 | audit needed |
| EPUB / EPUB3 | 65 | audit needed |
| CSV / TSV | 10 | audit needed |
| CSL / BibTeX / BibLaTeX / csljson / RIS / EndNote XML | 79 | audit needed |
| LaTeX / TeX / math | 21 | audit needed |
| DocBook | 22 | audit needed |
| RTF | 4 | audit needed |
| Shared ZIP / OPC package | 107 | audit needed |
| PDF import | 49 | audit needed |
| Legacy DOC / CFB | 7 | audit needed |

Latest registry evidence: `PandocFormatRegistry` records bounded wiki-family alias collision diagnostics for the `wiki` token suffix and `.wiki` MediaWiki/Vimwiki fixture-extension conflict while keeping `.wiki => mediawiki` extension inference unchanged. The slice keeps stable unsupported reader/writer reason payloads, empty native implementation records, `externalToolFree=true`, `directReaderParitySupported=false`, and `directWriterParitySupported=false`; native wiki reader/writer parity remains unsupported.

Current Pandoc counters: 3,442 PHP passes / 0 failures and 3,391 mapped upstream cases. Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`, focused `PandocFormatRegistryTest.php` (`1` file, `2,082` assertions, `0` failures), full `lanes/pandoc/tests` (`46` files, `80,170` assertions, `0` failures), `jq empty`, and `git diff --check`.
