# EPUB3 Package Metadata Context Slice

Slice: `pandoc-epub3-package-core-current-base-20260607T081107Z`
Base accepted HEAD: `e54667dcf3d17df8e001d9df1a3dbd7885b17703`

## Behavior

Native `EpubReader` now carries OPF package, metadata, and collection `xml:lang` / `dir` context into package review records:

- DC metadata entries inherit metadata/package context unless the element overrides it.
- OPF `meta` refinements inherit the same language and direction context.
- OPF metadata `link` records and resolved linked-record summaries preserve inherited language and direction.
- Creator/contributor detail summaries now expose inherited direction alongside existing language metadata.
- Collection metadata inherits the collection language/direction context.

This is a bounded EPUB support-library patch only. It does not expand the XHTML reader, run Pandoc, or invoke external package tools.

## Evidence

Red-first focused test:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Failed the new `inherits OPF metadata language and direction for review handoff` fixture before the reader change with `Expected: 'ar'` / `Actual: NULL`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed: `1 test files, 1751 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed: `epub3 package handoff self-test ok`.
- `php -l lanes/pandoc/src/EpubReader.php` passed.
- `php -l lanes/pandoc/tests/EpubReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php` passed.
- `git diff --check -- lanes/pandoc` passed with no output.

Status delta:

- `phpPass`: `1474 -> 1475`
- mapped native support cases: `1891 -> 1892`
- focused EPUB reader coverage: `+1 PASS case / +21 assertions`

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage` fixtures, DOM/XML parsing, `EpubReader` OPF metadata parsing, and the existing WordPress EPUB3 package handoff example.

Full upstream Pandoc/Haskell runner parity, external `zip`/`unzip`, Word/LibreOffice validation, complete EPUB media/rendering parity, and deeper XHTML-to-AST conversion remain out of scope for this slice.

## Non-Overlap

This does not repeat existing EPUB OCF container/rootfile handling, OPF manifest/spine parsing, nav/NCX target resolution, title-type refinement grouping, contributor role refinement parsing, accessibility metadata, fallback chains, media overlays, CFI fragments, or XHTML raw HTML handoff coverage.
