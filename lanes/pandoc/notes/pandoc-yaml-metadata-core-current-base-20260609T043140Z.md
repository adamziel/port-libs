# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T043140Z`

Accepted base: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`

## Scope

This slice stays inside native PHP YAML/front-matter metadata parsing. It does
not shell out to Pandoc, Cabal/Haskell runners, external YAML parsers, Word,
LibreOffice, zip/unzip, external converters, online services, live provider
tests, or live-service provider tests.

## Behavior

`MarkdownReader` now preserves YAML document marker comments as inert review
provenance in `yamlMetadataCommentProvenance`.

Covered marker shapes:

- explicit metadata opening marker comments such as `--- # source export front matter`;
- directive document-start marker comments after `%YAML`/`%TAG` preambles;
- metadata closing marker comments for both explicit and implicit front matter.

Each provenance record includes `context: document-marker`, the marker text,
the marker role (`opening`, `document-start`, or `closing`), an empty document
path, and the source line. Parsed metadata values are unchanged.

## Evidence

Red-first focused command after adding the marker-comment expectations:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4607 assertions, 1 failures` because
`yamlMetadataCommentProvenance` did not contain document-marker comments.

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4626 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2305 -> 2306`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2705 -> 2706`.
- New manifest counters:
  - `mappedYamlMetadataDocumentMarkerCommentCases`: `1`
  - `yamlMetadataDocumentMarkerCommentAssertions`: `14`

## Dependency Closure

No new support component is needed. This reuses native `MarkdownReader`
YAML/front-matter parsing, existing YAML comment provenance handoff,
`MarkdownReaderTest.php`, and the WordPress YAML metadata handoff example.
Full upstream Pandoc runner parity remains separate because it requires a
hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted YAML document placement, YAML version handling,
TAG directive handling, reserved directive handling, invalid TAG diagnostics,
source-line diagnostics for directives/scalars/collections, standalone/trailing
YAML comments, flow comments, aliases, anchors, merge diagnostics, explicit key
handling, typed scalar parsing, or writer-side YAML emission. It owns only
comments attached to YAML document markers.

## Follow-Up

Next YAML metadata work should target a non-overlapping native metadata gap
such as writer-side comment preservation, additional source-span provenance, or
downstream metadata consumer handoff.
