| Project | Focus | State | Progress | PHP Tests | Mapped Upstream | Unmapped | Next Gate | Commit |
| --- | --- | --- | ---: | ---: | --- | ---: | --- | --- |
| [libsqlite](lanes/libsqlite/lane-status.json) | Primary | PHP green, upstream gap | 99.6% | 6,290,284 pass / 0 fail | [1,589 / 1,589 (100.0%)](lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | 16d8081 |
| [LightningCSS](lanes/lightningcss/lane-status.json) | Active | PHP green, upstream gap | 99.8% | 9,280 pass / 0 fail | [2,445 / 3,532 (69.2%)](lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json) | 1,087 | Full upstream runner closure is partial: bounded Rust media test and... | pending isolate... |
| [gitoxide](lanes/gitoxide/lane-status.json) | Active | High coverage | 98.8% | 11,183 pass / 0 fail | [1,821 / 2,886 (63.1%)](lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json) | 1,065 | Cargo workspace blocked by sparse target files | 29e9ab4 |
| [markerPDF](lanes/markerpdf/lane-status.json) | Active | PHP green, upstream gap | 100.0% | 3,621 pass / 0 fail | [763 / 78 (978.2%)](lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json) | 0 | No GPU/model execution will be run for markerPDF under current user d... | pending fast ba... |
| [Readability/content rewrite engine](lanes/readability/lane-status.json) | Backlog | Active port | 85.0% | 154 pass / 0 fail | [1,578 / 1,984 (79.5%)](lanes/readability/UPSTREAM_TEST_MANIFEST.json) | 406 | No local blocker | cd2e8a0 |
| [pandoc](lanes/pandoc/lane-status.json) | Backlog | High coverage | 96.0% | 3,279 pass / 0 fail | [3,248 / 2,276 (142.7%)](lanes/pandoc/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | zip-selected-platform-attributes-e65eb3cbf |
| [quadrable](lanes/quadrable/lane-status.json) | Backlog | High coverage | 98.0% | 137 pass / 0 fail | [55 / 55 (100.0%)](lanes/quadrable/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | cd2e8a0 |
| [syncthing](lanes/syncthing/lane-status.json) | Backlog | PHP green, upstream gap | 99.0% | 350 pass / 0 fail | [350 / 658 (53.2%)](lanes/syncthing/UPSTREAM_TEST_MANIFEST.json) | 308 | No local blocker | cd2e8a0 |
| [difftastic](lanes/difftastic/lane-status.json) | Backlog | Active port | 80.0% | 279 pass / 0 fail | [272 / 586 (46.4%)](lanes/difftastic/UPSTREAM_TEST_MANIFEST.json) | 314 | Upstream runner parity unavailable | cd2e8a0 |
| [rclone](lanes/rclone/lane-status.json) | Backlog | High coverage | 98.0% | 512 pass / 0 fail | [512 / 2,553 (20.1%)](lanes/rclone/UPSTREAM_TEST_MANIFEST.json) | 2,041 | No local blocker | cd2e8a0 |
| [esbuild](lanes/esbuild/lane-status.json) | Backlog | Needs catch-up | 77.0% | 259 pass / 0 fail | [259 / 2,567 (10.1%)](lanes/esbuild/UPSTREAM_TEST_MANIFEST.json) | 2,308 | Release-extra upstream `make test-all` coverage remains static-only. | cd2e8a0 |
| [dolt](lanes/dolt/lane-status.json) | Parked | Parked | 69.0% | 249 pass / 0 fail | [315 / 613 (51.4%)](lanes/dolt/UPSTREAM_TEST_MANIFEST.json) | 298 | Parked | cd2e8a0 |

## Pandoc Format Pass-Rate Matrix

These rows use the inspected static upstream inventory and current local mapped case counters. Percentages above 100% mean the local PHP tests are more granular than the upstream case counter available for that format family; they do not claim upstream runner parity.

| Pandoc format family | Count basis | Local passing | Upstream denominator | Pass % |
| --- | --- | ---: | ---: | ---: |
| Markdown/CommonMark/GFM | `test/` Markdown fixture files and mapped Markdown cases | 439 | 1,096 | 40.1% |
| HTML/XML/JATS DOM | HTML/XML/JATS fixture files plus DOM mapped cases | 272 | 29 | 937.9% |
| JSON/native AST | `.native` expected artifacts and JSON/native mapped cases | 43 | 252 | 17.1% |
| DOCX/OpenXML | `docxOpenXmlCoreCases` and DOCX/OpenXML mapped cases | 89 | 35 | 254.3% |
| EPUB/EPUB3 | `epub3PackageCoreCases` and EPUB mapped cases | 57 | 9 | 633.3% |
| ODF/ODT/OpenDocument | `odfOpenDocumentCoreCases` and ODF/ODT mapped cases | 69 | 16 | 431.3% |
| Shared ZIP/OPC package | ZIP, OPC, and archive-compression upstream case counters | 104 | 67 | 155.2% |
| CSL/BibTeX/BibLaTeX/csljson citations | citation/BibTeX/CSL mapped cases and upstream citation counters | 75 | 8 | 937.5% |
| PDF/Typst boundary/provenance | PDF engine and Typst boundary counters | 45 | 17 | 264.7% |
| LaTeX/TeX/math | TeX/math reader-writer counters | 20 | 14 | 142.9% |
| DocBook/table geometry | DocBook command fixtures and table-geometry mapped cases | 16 | 16 | 100.0% |
| Legacy DOC/CFB | `legacyDocCfbCoreCases` | 7 | 7 | 100.0% |
| RTF | `rtfReaderCoreCases` | 4 | 3 | 133.3% |
| IPYNB/notebook | notebook package mapped case floor | 1 | 1 | 100.0% |
| Plain text | PlainWriter mapped case floor | 2 | 2 | 100.0% |
| Templates/YAML metadata | doctemplate/YAML mapped case floor | 35 | 35 | 100.0% |
| Unicode/charset/syntax highlighting | charset and syntax-highlighting counters | 9 | 9 | 100.0% |
| Media bag/resources | media-bag mapped case floor | 5 | 5 | 100.0% |
| Format registry/wiki/roff/rich package evidence | registry evidence counters, not direct conversion parity | 20 | 20 | 100.0% |
| Unsupported input format surfaces | registry format-direction count with no native PHP reader parity | 0 | 33 | 0.0% |
| Unsupported output format surfaces | registry format-direction count with no native PHP writer parity | 0 | 61 | 0.0% |

Methodology: upstream denominators come from `lanes/pandoc/notes/upstream-inventory.md` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, whose inventory records the pinned static Pandoc source inventory from `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. Local passing counters merge `mapped*Cases` from `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and current `lanes/pandoc/lane-status.json`; `phpPass`/`phpFail` come from `lanes/pandoc/lane-status.json`. Commands used: `jq` over the manifest and lane status JSON to list case counters, a local PHP aggregation pass to group `mapped*Cases` by format family, `git diff --check -- progress.md`, and `php tools/run-tests.php lanes/pandoc/tests`. No Pandoc binary, office suite, TeX/Typst engine, browser engine, Node tooling, or external validator was invoked.
