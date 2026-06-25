# Pandoc MediaBag Repair Provenance - 2026-06-25

Hook bead: `plib-h4a98`.

Scope: close the linked-resource repair provenance follow-up for MediaBag-style
resource handoff metadata. This stays in native PHP resource metadata and tests;
it does not invoke Pandoc, browsers, Node tooling, online services, live
providers, or external validators.

## Implementation

Added a bounded `MediaBag` implementation for Pandoc lane resource handoff. It
supports inserted data URI resources, linked-resource lookup, document fill, and
media extraction. Linked `image` and `link` nodes receive stable provenance
attributes describing the original URL, resolved media path, MIME type, MIME
source, and repair summaries.

The repair layer records normalized path matches, percent-decoded path matches,
case-fold collision disambiguation, extension/content-type disagreement repair
summaries, and duplicate linked-resource MIME groups. The collision diagnostics
from the earlier linked-resource follow-up remain covered while the new
provenance metadata is asserted across Markdown/WordPress/JSON handoff shapes.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/LatexWriterTest.php lanes/pandoc/tests/MediaBagTest.php`
  - 4 files, 4,870 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 15 files, 22,873 assertions, 0 failures.
