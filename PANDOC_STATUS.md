# Pandoc Status

Updated: 2026-06-15 UTC

Rule: a format is complete only when repo passing tests equals upstream tests.

ODF/ODT is marked ship-ready: 78 local mapped ODF/ODT cases / 20 upstream ODF/ODT cases, with 0 critical ODF/ODT gaps.

| Format / path | Repo passing tests | Upstream tests |
| --- | ---: | --- |
| ODF / ODT / OpenDocument (`odt`) | 20 | 20 |
| Markdown / CommonMark / GFM | 452 | 1,096 |
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

Latest EPUB3 evidence: `EpubPackageReader` reports OPF package-root authoring provenance, including id/version, unique-identifier binding, `xml:lang`, `dir`, `xml:base`, prefix diagnostics, custom attributes, base-resolution policy, package prefix reports, package/manifest/spine authoring reports, and package identity diagnostics.

Current Pandoc counters: 3,647 PHP passes / 0 failures and 3,684 mapped upstream cases. Verification passed after rebasing the EPUB package root authoring report slice onto current main `559492548e`: `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` (`1` file, `1,138` assertions, `0` failures); focused `OdfOdtShipReadinessStatusTest.php` (`1` file, `24` assertions, `0` failures); full `lanes/pandoc/tests` (`46` files, `85,915` assertions, `0` failures); `jq empty`; conflict-marker scan; and `git diff --check`.
