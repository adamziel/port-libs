# EPUB3 Package Fallback-Style Handoff

Slice: `pandoc-epub3-package-core-current-base-20260607T022758Z`
Base: `dceb129b94af76d8e90cb1d4f15360a8db272ff2`

## Behavior

`EpubReader` now preserves OPF manifest `fallback-style` ids and resolves bounded CSS fallback-style chains for non-spine asset review. The asset report records the fallback-style id, resolved CSS package part, CSS byte hash, chain items, and diagnostics for missing manifest ids, non-CSS terminal resources, external resources, encrypted targets, unreadable bytes, and cycles. The same asset report is exposed through `importReport` and the AST document attributes so WordPress handoff code can review the package asset policy without running external tools.

The WordPress EPUB3 package example now includes a slideshow asset with `fallback-style="style"` and verifies that the resolved CSS part and hash survive the handoff.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` failed before implementation because OPF `fallback-style` was not exposed (`1 test files, 1694 assertions, 1 failures`).
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 1729 assertions, 0 failures`.
- WordPress example self-test: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed.
- Syntax checks: `php -l` passed for changed PHP files.
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `EpubReader`, `ZipPackage`, `OpcPackagePath`, the EPUB asset-report handoff, the existing WordPress EPUB package example, and the focused lane PHP harness. No Pandoc, Cabal, Haskell runner, EPUBCheck, Word, LibreOffice, `zip`/`unzip`, browser renderer, online service, live provider test, or live-service provider test was executed.

Full upstream Pandoc runner parity remains blocked on hydrating the pinned upstream checkout and explicitly authorizing Haskell/Cabal runner work, not on local OPF fallback-style package support.

## Non-Overlap

This is separate from accepted EPUB container/rootfile parsing, OPF metadata, manifest/spine state, nav/NCX targets, XHTML raw-block handoff, remote-resource policy, normal OPF `fallback` chains, bindings, media overlays, OCF sidecars, vocabulary-prefix reporting, and CSS resource dependency scanning. This slice owns only OPF `fallback-style` asset metadata and CSS fallback review diagnostics.

## Follow-Up

Keep EPUB3 follow-up bounded to non-overlapping package semantics such as alternate renditions, media-overlay validation, remote-resource/package policy, or additional OPF validation. CSS cascade/style application, EPUBCheck parity, decryption, external fetches, XHTML normalization, and Pandoc/Cabal/Haskell runner execution remain out of scope for this isolated slice.
