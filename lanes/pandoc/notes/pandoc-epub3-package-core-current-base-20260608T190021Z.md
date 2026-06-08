# pandoc-epub3-package-core-current-base-20260608T190021Z

## Scope

- Lane: `pandoc`
- Accepted base: `aa709bdd261c62b8add6039e6ca5022e21d12391`
- Owned surface: EPUB3 package handoff under `EpubReader`.
- Upstream behavior cluster: static EPUB package review of CSS `@font-face`
  rules in referenced stylesheet assets.

This slice keeps execution bounded and native PHP only. It does not execute
Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`/`unzip`, browser
renderers, CSS engines, font shapers, online services, live provider tests, or
live-service provider tests.

## Behavior

`EpubReader::cssResourceReport()` now preserves CSS `@font-face` package
handoff metadata for EPUB stylesheets:

- aggregate and per-stylesheet font-face source counts;
- bounded `font-family`, `font-style`, `font-weight`, `font-display`, and
  `unicode-range` descriptor metadata;
- `local(...)` source names;
- `url(...)` package font candidates with MIME/media type, manifest id,
  encryption state, missing target diagnostics, and remote-source diagnostics;
- deterministic font family inventory for import review.

The existing WordPress EPUB3 package example now asserts that encrypted package
font sources remain visible as metadata-only package references.

## Evidence

- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 2499 assertions, 0 failures`
- Red-first probe after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 2501 assertions, 1 failures`
  - Missing aggregate `fontFaceSourceCount` proved the old EPUB CSS report
    counted `@font-face` rules but did not preserve source metadata.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 2542 assertions, 0 failures`
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  -> `epub3 package handoff self-test ok`

Focused delta: `+1` PHP PASS case and `+43` focused assertions for
`EpubReaderTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage` package reader, EPUB OPF/CSS asset reporting, package reference
resolver, encrypted-resource metadata, and the WordPress EPUB3 package handoff
example. Full CSS cascade/layout/font parsing, font decoding, browser rendering,
Pandoc/Cabal/Haskell runner parity, and external converter parity remain out of
scope.

## Non-Overlap

This is distinct from recent EPUB package handoffs covering scripted XHTML,
CSS image references, CSS remote resources, navigation/NCX, CFI metadata,
media fragments, OPF metadata/link/bindings/fallbacks, OCF sidecars, duplicate
manifest parts, encryption preflight, and obfuscated-font metadata. It adds the
static CSS `@font-face` descriptor/source handoff only.

## Follow-Up

Useful follow-ups are CSS cascade/export policy, EPUB accessibility/package
diagnostics, nav-to-AST handoff, or media-overlay edge metadata, all still
bounded to native PHP and external-tool-free verification.
