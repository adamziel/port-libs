# XML/HTML5 DOM ARIA IDREF provenance - 2026-06-30

Slice: `plib-km3im`

## Scope

- Expanded HTML ARIA ID reference summaries with target records, per-token reference records, duplicate target ID detection, issue codes, and aggregate resolution fields.
- Covered invalid tokens, missing targets, duplicate tokens, duplicate target IDs, and single-reference attributes carrying multiple IDs.
- No parser, serializer, browser, validator, or external-tool dependency was introduced.

## Direct-format parity

- Direct reader parity remains unchanged.
- This is bounded XML/HTML DOM reviewer metadata that preserves raw HTML handoff behavior while making accessibility IDREF ambiguity inspectable.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
