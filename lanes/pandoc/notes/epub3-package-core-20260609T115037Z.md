# EPUB3 Package Core Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T115037Z`

Base accepted HEAD: `329b990b1079e0c81d2c156d545b769dc66d69c3`

## Behavior

Added a native extracted-directory EPUB3 reader that maps:

- OCF `META-INF/container.xml` rootfile discovery.
- OPF package metadata, metadata refinements, manifest, media types, properties, and spine order.
- EPUB3 XHTML nav `toc` and `landmarks` entries, including nested toc children.
- NCX `navMap` fallback entries with play order and fragments.
- Linear spine XHTML headings, paragraphs, links, images, blockquotes, code spans, hard line breaks, and lists into the existing `AstNode` shape.
- WordPress block output through the existing `WordPressBlockWriter`.

This deliberately does not parse ZIP bytes. It accepts an extracted package directory so it can compose with the bounded ZIP/OPC package primitive without invoking `zip`, `unzip`, Pandoc, browser renderers, or online services.

## Evidence

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
- Result: `1 test files, 2315 assertions, 0 failures`

New focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
- Result: `1 test files, 90 assertions, 0 failures`

Focused lane aggregate after this slice:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `2 test files, 2405 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-import.php --self-test`
- Result: `EPUB3 package WordPress smoke passed`

## Dependency Closure

No new support component is needed for extracted EPUB packages. This slice reuses PHP DOM/XML, the existing Pandoc-lane AST, and `WordPressBlockWriter`.

Follow-up: connect `EpubPackageReader` to the accepted ZIP/OPC package primitive when a slice owns archive expansion, keeping archive validation and package parsing separate.

## Non-overlap

This avoids the queued OPF date-events preflight patch, which extends a compact `EpubPackage` summary surface. This patch adds a separate `EpubPackageReader` AST conversion surface for extracted EPUB3 package directories.
