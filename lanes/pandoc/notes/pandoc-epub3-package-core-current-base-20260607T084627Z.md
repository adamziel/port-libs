# pandoc-epub3-package-core-current-base-20260607T084627Z

Slice: EPUB3 OPF vendor metadata handoff for Pandoc-style `ibooks:*` and `calibre:*` metadata.

Source truth:
- Pinned Pandoc `src/Text/Pandoc/Writers/EPUB.hs` reads OPF metadata extension maps through `metadataFromMeta` and writes vendor fields through `metadataElement`, including `ibooks` and `calibre` field maps.
- Pinned Pandoc `addMetadataFromXML` folds existing OPF `<meta property/name="ibooks:*">` and `<meta property/name="calibre:*">` entries into the same vendor metadata maps.

Implementation:
- `EpubReader` now builds `metadata.vendorMetadata` from already parsed OPF `metaProperties`.
- The report groups entries by `ibooks` and `calibre`, preserves property name, field name, text/content-derived value, id/refines/language/direction, resolved property vocabulary metadata when present, and empty field/value diagnostics.
- The summary is propagated through `metadata`, `importReport.metadata`, and document metadata attributes without changing the existing raw `metaProperties` shape.
- The WordPress EPUB3 package handoff smoke now includes `ibooks:specified-fonts`, `ibooks:version`, `calibre:series`, and `calibre:series_index` metadata and checks the document metadata handoff.

Focused evidence:
- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 1751 assertions, 0 failures`.
- Red-first after adding the focused test: same command failed only `reports OPF vendor metadata fields for package review handoff`, with `vendorMetadata.present` missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 1767 assertions, 0 failures`.
- Local example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP `ZipPackage`, OPF DOM parsing, metadata-property vocabulary resolution, import-report, and WordPress handoff paths.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

Non-overlap:
- This does not repeat existing EPUB container/rootfile/nav/NCX/spine/guide/collection/accessibility/fallback/media-overlay/asset/XHTML behavior. It adds a bounded metadata-extension contract specifically for OPF vendor fields that Pandoc carries through EPUB metadata maps.

Next:
- A useful EPUB follow-up would be a bounded OPF contributor/creator metadata date or identifier scheme refinement edge, or parser-level XHTML body conversion coverage; avoid another raw metadata inventory-only patch unless it changes a real handoff contract.
