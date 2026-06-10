# EPUB nav ARIA label handoff slice

Hook: `plib-6ytb` (`mol-polecat-work`, `plib-wisp-15bxn`)

## Scope

- Added bounded native PHP ARIA label metadata for EPUB XHTML navigation documents.
- `readNavDocument()` now builds an in-memory nav-document `id => text` index and records section `aria-label` / `aria-labelledby` metadata.
- `readNavList()` now records item label metadata from the anchor/span label element, including resolved and missing `aria-labelledby` IDs.
- Added `nav.accessibility` aggregate counts and diagnostics for missing nav ARIA label references.
- Propagated the compact fields into primary nav policy, page-list entries, outline rows, and combined `navigation.items` handoff rows.

## Non-overlap

This does not change existing nav structure diagnostics, duplicate-target diagnostics, page-list parsing, NCX parsing, trigger/switch scans, CFI/media-fragment handling, or OPF metadata parsing. It preserves raw `attributes` / `labelAttributes` while adding normalized reviewer metadata.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed 1 file / 4001 assertions / 0 failures.
- Post-rebase `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 60211 assertions / 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed.

No Pandoc, EPUBCheck, browser renderer, zip/unzip, Node, office, TeX, Jupyter, external validator, or network service was invoked.
