# Pandoc XML/HTML5 DOM Details Disclosure Provenance

Implemented one bounded native PHP XML/HTML5 DOM support slice:

- `XmlHtmlDom` now preserves richer `<details>` disclosure review metadata:
  open/closed state, raw and trimmed `name`, same-name group position, group
  size, group open count, and same-group open-conflict provenance.
- Direct `<summary>` children are now carried as deterministic records with
  index, id, text, primary-summary flag, and direct child-element count.
- `<summary>` elements now report their direct parent details id/name, summary
  index, and primary-summary flag while loose summaries remain inert and
  parentless.
- The focused fixture verifies deterministic raw HTML serialization and
  WordPress raw-block propagation for grouped, missing-summary, and loose
  summary cases.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 1428 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 71009 assertions, 0 failures

Metric movement:

- `phpPass`: 3205 -> 3206
- `phpFail`: 0
- `mappedXmlHtmlDomDetailsDisclosureCases`: 1
- `xmlHtmlDomDetailsDisclosureAssertions`: 42

No Pandoc, browser renderer, online sanitizer, external validator, online
service, live provider test, or live-service provider test was invoked.

This does not repeat accepted XML/HTML5 DOM slices for form ownership, ordered
list ordinals, outline metadata, time/data/ruby semantics, dialog/popover
state, quote attribution, iframe `srcdoc`, media resources, document metadata,
or active-content provenance. A useful follow-up would be a separate bounded
slice for command/menu activation metadata if the lane needs more interactive
HTML reviewer state.
