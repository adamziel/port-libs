# ODF OpenDocument Link Event Listener Metadata

Slice: `pandoc-odf-open-document-core-current-base-20260608T215319Z`
Base accepted HEAD: `58f31919899f7fed2d6c14d26071a889af1e2099`

## Behavior

- Preserves `office:event-listeners` under `text:a` links as inert review metadata.
- Maps `script:event-listener` attributes (`event-name`, `language`, `macro-name`, `xlink:href`, `type`, `show`, `actuate`) into `odfLinkMetadata.eventListeners`.
- Emits bounded `data-odf-link-event-*` attributes through Markdown and WordPress handoff, while keeping raw event XML out of rendered output.
- Adds `importReport.content.eventListenerCount` for source-review accounting.

## Source Truth

This is bounded ODF/OpenDocument support-library behavior under `lanes/pandoc/**`. The slice follows the existing ODF reader contract that keeps macro/script-like source constructs inert and reviewer-visible, matching the adjacent field, DDE, script macro, and link metadata handoffs already present in `OdfReaderTest.php`.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed with `1 test files, 2401 assertions, 1 failures` because `eventListenerCount` was not mapped.
- Final focused ODF suite: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2422 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP ODF DOM traversal, `AstNode` link attributes, Markdown/WordPress attribute serializers, and lane-local ODT package fixtures. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Follow-up

A non-overlapping next ODF slice could preserve another inert source metadata surface such as `draw:layer` provenance or data-pilot consumer metadata. Macro execution and live office converter parity remain out of scope for this lane slice.
