# pandoc-epub3-package-core-current-base-20260606T000559Z

Base accepted HEAD: `996d008a6d589439433524500ecf697af2eedb4a`

## Scope

Implemented bounded EPUB3 package support for legacy NCX `pageList` handoff when an EPUB3 HTML nav page-list is absent. The slice parses NCX `pageTarget` entries into package-relative targets and uses them as `pageBreaks` metadata for WordPress/document handoff while preserving nav page-list precedence when present.

This is a native PHP support-library slice under `lanes/pandoc/**`; it did not shell out to Pandoc, Word, LibreOffice, `zip`/`unzip`, ZipArchive, browser renderers, Haskell runners, online services, or live providers.

## Behavior

- `EpubReader::readNcxDocument()` now exposes NCX `pageList` entries with `id`, `type`, `value`, `playOrder`, `class`, title, target, package part, fragment, and diagnostics.
- `EpubReader::pageBreakReport()` still prefers EPUB3 nav `page-list` entries, and falls back to NCX `pageList` only when the nav page-list is empty.
- Page-break report items now include source metadata (`nav` or `ncx`) plus source diagnostics, while retaining the existing `navDiagnostics` compatibility key.
- WordPress EPUB3 package handoff example now includes an NCX-only page-list self-test path.

## Focused Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1113 assertions, 0 failures
```

Red-first test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1113 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1158 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `ZipPackage` package reader, DOM/XML parsing already used by `EpubReader`, package-reference diagnostics, AST handoff, and the WordPress EPUB handoff example.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout and Cabal runner dependency closure; no Haskell runner was attempted in this isolated slice.

## Non-overlap

This does not repeat accepted EPUB3 container, OPF manifest/spine, nav/NCX TOC, XHTML asset handoff, media-overlay, CFI, encryption, metadata sidecar, or mimetype placement coverage. The new behavior is limited to NCX `pageList` fallback page-break handoff when the HTML nav page-list is absent.
