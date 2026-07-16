# EPUB3 Package Navigation Outline Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T050538Z`
Base: `5d02a10932dbbd350c989c1902aead80ac5c366a`
Date: 2026-06-09 UTC

## Behavior

This slice adds bounded native EPUB3 package-review support for a
`navigationOutline` handoff. `EpubReader` now exposes the outline on the
top-level package result, import report, and WordPress document attributes.

The outline selection policy is intentionally small:

- use the primary EPUB nav `toc` section when it is available;
- fall back to the legacy NCX `navMap` when the nav document has no TOC;
- use the nav fallback list only when neither of those sources has outline
  items.

The report preserves nested items, flattened items, target provenance, mapped
spine counts, missing/external target counts, diagnostics, max depth, and an
escaped inert HTML review fragment with a SHA-256 digest. The HTML uses data
attributes rather than active links, so remote targets remain unfetched and
local package targets stay metadata-only for review.

## Evidence

Red-first behavior:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: failed before source implementation because `navigationOutline` was
  absent; the run reached `1 test files, 3648 assertions, 1 failures`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3680 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`

Syntax and diff checks:

- `php -l lanes/pandoc/src/EpubReader.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Result: no syntax errors
- `php -r 'json_decode(...)'` for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- Result: both JSON files decoded successfully
- `git diff --check -- lanes/pandoc`
- Result: passed

Focused delta: +1 PHP TestRunner PASS case and +33 focused assertions in
`EpubReaderTest.php`. `lane-status.json` moves `phpPass` from `2346` to
`2347`; `UPSTREAM_TEST_MANIFEST.json` moves mapped EPUB/Pandoc support from
`2741` to `2742`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `EpubReader`
nav/NCX parsing, existing navigation target reconciliation, the existing
WordPress document metadata handoff, the focused PHP test runner, and the
lane-local WordPress EPUB package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
`zip`/`unzip`, EPUBCheck, browser renderer, external converter, online
service, live provider test, or live-service provider test was executed. Full
upstream Pandoc runner parity remains a separate upstream-runner dependency
task requiring hydrated pinned upstream sources and Haskell test executables.

## Non-Overlap

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
OPF metadata/vendor fields, effective per-spine rendition metadata, raw itemref
property parsing, alternate renditions, nav/NCX target reconciliation,
nav/NCX provenance, primary navigation target policy, page-list/page-break
handoff, guide or collection reporting, fallback chains, bindings, media
overlays, remote resource reconciliation, content feature reconciliation,
XHTML/CSS resource scans, cover/asset reports, encryption/font preflight,
sidecar reporting, or EPUB CFI/media-fragment propagation. It owns only the
bounded navigation outline review report and inert HTML handoff derived from
already-parsed nav/NCX package data.

Root harness status: not run - isolated micro-slice.
