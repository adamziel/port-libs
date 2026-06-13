# JATS/BITS funding and acknowledgment review diagnostics (2026-06-13)

This slice maps one bounded XmlHtmlDom JATS/BITS funding and acknowledgment
review case on current main `de88a856`.

`XmlHtmlDom::summarizeJatsFrontMatter()` now records metadata-only summaries for
`funding-group`, `award-group`, `funding-source`, `award-id`, and `ack`
sections. The packet includes funding-source identifiers, award IDs,
duplicate/missing award diagnostics, safe linked/missing reference IDs, and
acknowledgment hashes/lengths while keeping `directReaderParity=false`.

Raw citation payload text remains blocked from the review packet. The fixture
checks that a sentinel mixed-citation string is absent from the encoded packet.

No Pandoc binary, XML validators, browser renderers, Node tooling, online
services, live provider tests, or external validators were invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 file,
  3085 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 78453 assertions,
  0 failures
