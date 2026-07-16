# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260603T221151Z`

Base accepted HEAD: `ee5bab2f1ee2c0907fe52d29b7278104c9b95fba`

## Behavior Added

- Added `OpenDocumentPackage` as a bounded native PHP ODT package helper.
- Validates the ODT `mimetype` entry as the first stored ZIP entry with
  `application/vnd.oasis.opendocument.text`.
- Parses `META-INF/manifest.xml` in the ODF manifest namespace and exposes
  `manifest:file-entry` paths, media types, versions, and sizes.
- Maps `content.xml` `text:h` and `text:p` nodes into the existing Pandoc-like
  AST, including text links, repeated spaces, line breaks, and embedded images.
- Parses `styles.xml` style names, families, parent style names, and display
  names.
- Parses `meta.xml` generator, title, description, subject, keywords, language,
  creators, dates, and `meta:user-defined` fields.
- Added `wordpress-odt-package-preflight.php`, an in-memory ODT smoke that maps
  package metadata and content into WordPress blocks without LibreOffice or
  Pandoc.

## Source Truth

- Upstream Pandoc pinned source `0640c4c9859aa5a3ede082c190fcd5883c24ac83`,
  `src/Text/Pandoc/Writers/ODT.hs`, builds ODT archives from `mimetype`,
  `content.xml`, `styles.xml`, `meta.xml`, and `META-INF/manifest.xml`.
- The upstream writer emits `manifest:manifest` in
  `urn:oasis:names:tc:opendocument:xmlns:manifest:1.0`, a root file-entry for
  `application/vnd.oasis.opendocument.text`, and `meta.xml` fields for title,
  description, subject, keywords, language, creators, dates, and user-defined
  metadata.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odt-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - Result: `1 test files, 61 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `6 test files, 2754 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-odt-package-preflight.php --self-test`
  - Result: `odt package preflight self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new external support component is needed. This slice reuses the accepted
native PHP `ZipPackage` reader/writer and the existing AST/WordPress block
writer. It does not invoke Pandoc, Word, LibreOffice, zip/unzip, external
template engines, TeX/PDF engines, Haskell test binaries, online services, or
network conversion services.

## Non-Overlap

This patch is additive on top of accepted ZIP package, OPC XML relationships,
YAML metadata, doctemplate, and CSL/citation work. It does not edit dashboard
or root progress files and does not touch DOCX body parsing, EPUB, PDF, legacy
DOC/CFB, BibTeX/BibLaTeX, or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep richer ODT list/table/style inheritance, automatic style resolution,
footnotes, tracked changes, embedded object directories, and full ODT reader
parity as separate bounded slices. Minimal DOCX document-part parsing and
upstream Cabal runner planning remain separate gates.
