# pandoc-epub3-package-core-current-base-20260609T023134Z

Slice: EPUB3 XHTML semantic pagebreak fallback on accepted base
`a90c290373fc105bb0c871a8045e20501401691f`.

## Behavior

EPUB packages sometimes omit both the EPUB3 nav `page-list` and the legacy NCX
`pageList`, while still marking page boundaries inside spine XHTML with
`epub:type="pagebreak"`. The reader already preserved those XHTML semantic
items in the content-resource report, but the package-level `pageBreaks`
handoff stayed empty.

This slice teaches `EpubReader` to use XHTML semantic pagebreaks as a fallback
source only when nav and NCX page lists are absent. The fallback preserves:

- visible text/title/ARIA labels;
- generated package targets from source part plus element id;
- source attributes, classes, and element names;
- manifest id, spine index/idref, linear status, and per-spine block
  `pageBreaks`;
- import-report and document-level `pageBreaks` metadata.

Existing nav page-list and NCX pageList precedence is unchanged.

## Focused Evidence

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed in `builds EPUB page-break report from XHTML semantic pagebreaks when
  nav page lists are absent` because `pageBreaks.present` was still `false`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3466 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed with `epub3 package handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/EpubReader.php`
  passed.
  `php -l lanes/pandoc/tests/EpubReaderTest.php`
  passed.
  `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
  passed.
- `git diff --check -- lanes/pandoc`
  passed.

Assertion delta: +28 focused assertions and +1 focused PHP PASS case in
`EpubReaderTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`EpubReader`, OPF manifest/spine resolution, XHTML DOM scanning, the existing
semantic `epub:type` handoff, and the WordPress EPUB3 package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, EPUBCheck,
zip/unzip, Word, LibreOffice, browser renderer, JavaScript runtime, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted OCF mimetype/container/rootfile validation, OPF
metadata/manifest/spine parsing, OPF prefix vocabulary resolution, nav XHTML
page-list parsing, NCX pageList parsing, navigation/spine reconciliation,
guide/collection links, alternate renditions, fallback chains, bindings, media
overlays, XHTML resource/script/link/form/switch/trigger/semantic scanning,
remote-resource reconciliation, encryption, obfuscated-font preflight, EPUB CFI
fragments, media fragments, OCF sidecars, or ZIP integrity work.

The covered gap is strictly package-level page-break fallback from already
scanned XHTML `epub:type="pagebreak"` semantic markers when the two navigation
page-list sources are absent.

## Follow-Up

Useful non-overlapping EPUB3 follow-ups include XHTML-to-AST conversion,
nav-to-AST rendering, CSS cascade/export policy, richer encrypted-resource
review decisions, EPUBCheck-style static validation diagnostics, and full
Haskell/Pandoc runner comparison as separate bounded work.
