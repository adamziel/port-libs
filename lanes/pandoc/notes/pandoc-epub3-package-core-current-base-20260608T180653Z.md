# EPUB3 Package Scripted XHTML Content Handoff

Slice: `pandoc-epub3-package-core-current-base-20260608T180653Z`

Accepted base: `1d10c26783e331f072073a9dc0eef297e722aedb`

## Scope

This slice adds bounded native EPUB3 package inspection for scripted XHTML content. `EpubReader` now reports inert review metadata for:

- inline script elements;
- local package script sources, including byte length, CRC32, SHA-256, manifest id/media type, and encryption visibility;
- remote script sources without fetching them;
- inline event-handler attributes such as `onload` and `onclick`;
- `javascript:` URL references.

The metadata is exposed through XHTML asset records, package resource reports, import reports, and AST document attributes so WordPress import queues can flag scripted EPUB content without executing JavaScript or invoking browser tooling.

## Evidence

Red-first focused test:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Result before implementation: `1 test files, 2457 assertions, 1 failures` because `scriptCount` metadata was absent from the XHTML resource report.

Final focused test:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Result after implementation: `1 test files, 2499 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`

Result: `epub3 package handoff self-test ok`.

Focused delta: `+1` PHP pass case and `+44` focused assertions over the prior EPUB reader baseline.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing EPUB ZIP/package reader, DOM/libxml `NONET` XHTML inspection, package reference resolver, AST metadata handoff, and WordPress example path.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal solver/build/test command, Haskell runner, browser renderer, JavaScript runtime, external validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is separate from accepted EPUB3 OCF/container, OPF metadata, spine, nav/NCX, missing non-spine asset, vendor metadata, and raw XHTML block handoff work. It only adds static scripted-content review metadata for EPUB XHTML assets.

## Follow-Up

Next EPUB3 work should stay non-overlapping: CSS resource handoff, media-overlay timing metadata, accessibility/package diagnostics, or additional nav-to-AST behavior.
