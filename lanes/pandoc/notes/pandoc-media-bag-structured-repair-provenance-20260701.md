# Pandoc MediaBag Structured Repair Provenance Follow-Up

Session: `port_libs/polecats/1764`
Hook: `plib-h4a98`
Micro-slice: `pandoc-media-bag-structured-repair-provenance-20260701`

## Scope

This follow-up keeps the accepted MediaBag linked-resource repair diagnostics
and summary strings, and adds structured metadata derived from them:

- `pathRepairReasons` on directory and media item records
- `extractionPathRepairReasons` on extracted media entries
- structured `mimeRepair` records with kind, source extension, inferred MIME,
  normalized MIME, and repair action for extension/content-type disagreements
- `data-pandoc-media-path-repair-reasons`,
  `data-pandoc-media-mime-repair-kind`, and
  `data-pandoc-media-mime-repair-action` attrs on mapped resources

## Non-Overlap

The earlier `pandoc-media-bag-repair-provenance-20260613T0655Z` slice already
added normalized path repair summaries, percent-decoded path summaries,
extension/content-type disagreement summaries, duplicate linked-resource MIME
groups, and case-folded extraction collision disambiguation. This slice does
not change those diagnostics or summary strings; it exposes the same decisions
as structured records and stable attrs for review consumers.

No Pandoc, browser, Node tooling, online service, live provider, external
validator, office suite, TeX/browser engine, Typst execution, Jupyter, or
zip/unzip tool was invoked.

## Evidence

Focused validation:

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
- Result: `1 test files, 262 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php lanes/pandoc/tests/EpubWriterTest.php lanes/pandoc/tests/PandocConverterTest.php`
- Result: `3 test files, 528 assertions, 0 failures`

Broad lane attempt:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `534 test files, 142304 assertions, 8912 failures`
- The broad failures were outside this MediaBag slice, visible in existing
  Markdown metadata/native-span, nested footnote, HTML/native reader,
  TableGeometry, and UnicodeText baselines.

Manifest delta:

- `mappedMediaBagStructuredRepairProvenanceCases`: `1`
- `mediaBagStructuredRepairProvenanceAssertions`: `10`

## Next Task

Keep future MediaBag work bounded to resource metadata handoff surfaces that
remain unrepresented, rather than changing resource bytes or fetching external
targets.
