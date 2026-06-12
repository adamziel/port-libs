# ODF manifest version and view-mode summary provenance

Slice: `plib-dlpy0` (`20260612T023157Z`)

## Scope

- Extended native ODF/ODT package ingestion manifest media-type summaries with bounded aggregate provenance for `manifest:version` and `manifest:preferred-view-mode`.
- The summary now exposes total counts, unique values, and manifest-order handoff rows for versioned and preferred-view-mode file entries.
- Per-media-type summary rows also expose counts and unique values so package reviewers can identify affected ODF part classes without scanning every manifest entry.

## Parity impact

This is package metadata/provenance only. It does not alter document text, inline/block construction, media extraction, or direct-format rendering parity.

## Verification target

- Focused: `lanes/pandoc/tests/OdfReaderTest.php`
- Full lane: `lanes/pandoc/tests`
