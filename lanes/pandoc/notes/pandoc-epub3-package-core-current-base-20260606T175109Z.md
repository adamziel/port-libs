Slice: pandoc-epub3-package-core-current-base-20260606T175109Z
Lane: pandoc
Base accepted HEAD: bd271365a39bc8cc84f04507c8a4161eee64c7c5

Behavior implemented:

- Added bounded EPUB XHTML `srcset` resource scanning in `EpubReader`.
- `img` and `source` `srcset` attributes are expanded into individual package resource references.
- Each candidate keeps its candidate index, original candidate text, descriptor, resolved package target, OPF manifest metadata, byte metadata, missing diagnostics, and remote-resource diagnostics.
- The existing XHTML resource report, remote-resource reconciliation, import report, and AST raw-HTML block `contentReferences` now receive the responsive-image candidates.

Focused evidence:

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 1567 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 1608 assertions, 0 failures`.
- Added one PHP PASS case and 41 focused assertions.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` printed `epub3 package handoff self-test ok`.
- PHP syntax checks passed for `lanes/pandoc/src/EpubReader.php`, `lanes/pandoc/tests/EpubReaderTest.php`, and `lanes/pandoc/examples/wordpress-epub3-package-handoff.php`.

Dependency closure:

- No new support component is needed. This reuses the native PHP `EpubReader`, `ZipPackage` fixture builder, and focused lane test harness.
- No Pandoc, Cabal, Haskell runner, zip/unzip, ZipArchive, EPUBCheck, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was run.

Non-overlap:

- This does not repeat the accepted XML/HTML5 DOM `srcset` sanitizer work. That support normalizes/sanitizes raw HTML fragment URL attributes; this slice maps EPUB package-level XHTML `srcset` candidates into OPF/package resource handoff metadata.
- This does not repeat earlier EPUB slices for OPF metadata links, cover candidates, non-spine fallback chains, OCF sidecars, triggers, switch content, or remote OPF manifest resources.

Next task:

- Continue EPUB3 package parity with a distinct handoff gap such as CSS resource dependency reporting, media fallback-chain review metadata, or bounded XHTML-to-AST normalization.
