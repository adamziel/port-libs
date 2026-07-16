# Pandoc EPUB3 Package Core Current Base

Slice: `pandoc-epub3-package-core-current-base-20260603T220950Z`

Base accepted HEAD: `ee5bab2f1ee2c0907fe52d29b7278104c9b95fba`

## Behavior Added

- Added `EpubPackage` as a bounded native PHP EPUB package preflight helper.
- Validates EPUB OCF ZIP requirements:
  - `mimetype` exists as the first ZIP entry.
  - `mimetype` is stored without compression.
  - `mimetype` content is exactly `application/epub+zip`.
  - `META-INF/container.xml` uses the OCF namespace and declares an OPF
    `application/oebps-package+xml` rootfile.
- Parses OPF package XML for:
  - version, unique identifier, Dublin Core title/creator/language/identifier,
    `dcterms:modified`, and legacy `meta name="cover"` metadata.
  - manifest item ids, hrefs, media types, properties, fallback ids, and
    resolved package part names.
  - spine itemrefs, linear flags, itemref properties, and reading-order parts.
- Extracts EPUB navigation:
  - EPUB3 XHTML `nav` item with `epub:type="toc"` and nested depth.
  - EPUB2/legacy NCX fallback through `spine toc` or first NCX manifest item.
- Summarizes XHTML, CSS, image, cover-image, nav, NCX, and reading-order assets
  into a WordPress import handoff.
- Added `wordpress-epub3-package-preflight.php` as a local smoke example for
  Data Liberation import planning.

## Source Truth

- This slice is based on the accepted `epub3-package-core` dependency row plus
  standard EPUB OCF/OPF package semantics: OCF `mimetype`/`container.xml`, OPF
  metadata/manifest/spine, EPUB3 XHTML nav documents, and NCX fallback.
- It reuses the accepted native `ZipPackage` reader/writer and `OpcPackagePath`
  internal path normalization. It does not use external `zip`/`unzip`, Pandoc,
  Haskell test binaries, office suites, TeX/PDF engines, online services, or
  browser/HTML conversion engines.

## Verification

- Red check before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Result: expected missing-class failures for `PortLibs\Pandoc\EpubPackage`.
- Syntax:
  - `php -l lanes/pandoc/src/EpubPackage.php`
    - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/EpubPackageTest.php`
    - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
    - Result: no syntax errors.
- Focused tests:
  - `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Result: `1 test files, 49 assertions, 0 failures`.
- Full Pandoc lane:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `6 test files, 2742 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
  - Result: `epub3 package preflight self-test ok`.
- JSON status/manifest:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No external support component is needed. This slice activates the bounded native
PHP `epub3-package-core` row by reusing existing ZIP package primitives and
OPC-style safe path normalization. XHTML chapter-to-AST conversion, EPUB
metadata refinements beyond the package preflight, DOCX/OpenXML document-part
parsing, ODT, PDF, CSL style XML, BibTeX/BibLaTeX, and upstream Cabal runner
dependency closure remain separate gates.

## Non-Overlap

This is additive EPUB package work. It does not touch Markdown/HTML reader
behavior, Markdown/WordPress writers, ZIP entry metadata, OPC relationship graph
behavior, doctemplates, YAML metadata, CSL citation clusters, DOCX body parsing,
ODT, PDF, or upstream-runner dependency-audit surfaces.

## Follow-Up

Next EPUB work should parse selected spine XHTML parts into the existing Pandoc
AST/WordPress handoff path and enrich EPUB metadata handling. Keep that separate
from DOCX, ODT, PDF, CSL/BibTeX, and full upstream-runner Cabal planning.
