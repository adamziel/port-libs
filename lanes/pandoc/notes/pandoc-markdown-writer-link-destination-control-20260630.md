# Pandoc Markdown Writer Link Destination Control Slice

Date: 2026-06-30
Bead: plib-m2oh4

## Scope

`MarkdownWriter` now serializes Markdown/CommonMark/GFM link and image destinations
without leaking raw ASCII control bytes. Controls in destinations are percent
encoded before Markdown syntax selection, while parenthesized, quoted, backslash,
spaced, empty, and angle-token destinations use angle-wrapped form with delimiter
escaping.

Link and image titles now normalize ASCII controls to spaces before title escaping,
so inline and reference definitions stay on one logical line.

No upstream Pandoc, browser, office suite, TeX/PDF engine, online validator, or
external service was invoked.

## Coverage

- `MarkdownWriterLinkDestinationControlSurgeTest.php` covers 16 mapped cases across
  inline links, images, reference links, and reference images.
- Cases include NUL, TAB, LF, CR, FF, Unit Separator, DEL, parenthesized
  destinations, and title control normalization.

## Validation

```bash
php -l lanes/pandoc/src/MarkdownWriter.php
php -l lanes/pandoc/tests/MarkdownWriterLinkDestinationControlSurgeTest.php
php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterLinkDestinationControlSurgeTest.php
```

Result: 1 file, 33 assertions, 0 failures.

## Metric Delta

- `lane-status.json` `phpPass`: `465 -> 466`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2406 -> 2407`
- Added `mappedMarkdownWriterLinkDestinationControlCases: 16`
