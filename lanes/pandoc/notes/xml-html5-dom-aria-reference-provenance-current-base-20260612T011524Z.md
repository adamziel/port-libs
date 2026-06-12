# XML/HTML5 DOM ARIA reference provenance

Bead: `plib-dpyhx`
Date: 2026-06-12 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `35cc19ad82`

## Behavior

`XmlHtmlDom` reviewer summaries now preserve ARIA ID-reference relationship
provenance for bounded HTML fragments. The new `ariaReferences` summary covers:

- ID-reference lists: `aria-controls`, `aria-describedby`, `aria-flowto`,
  `aria-labelledby`, and `aria-owns`;
- single ID references: `aria-activedescendant`, `aria-details`, and
  `aria-errormessage`;
- raw values, tokenized IDs, duplicate IDs, invalid tokens, present target IDs,
  missing target IDs, syntax validity, and resolved-state metadata.

Raw HTML serialization and the existing `ariaAttributes` map remain unchanged.

No Pandoc, browser renderers, online sanitizers, external validators, online
services, live provider tests, or live-service provider tests were invoked.

## Accounting

- `phpPass`: `3221 -> 3222`
- `phpFail`: `0`
- `mappedXmlHtmlDomAriaReferenceCases`: `+1`
- `xmlHtmlDomAriaReferenceAssertions`: `+32`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 1492 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 71644 assertions, 0 failures`
