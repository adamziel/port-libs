# XML/HTML5 DOM Table Header Reference Provenance

This follow-up adds bounded `XmlHtmlDom` table-cell review metadata for source
HTML `headers` references without invoking browsers, Node tooling, online
services, live providers, external validators, or Pandoc.

`XmlHtmlDom::summarizeHtmlFragment()` now resolves each `td`/`th` `headers`
token against header cells in the nearest owning table and exposes:

- token-ordered `headerReferences` records;
- resolved header IDs, text, scope, and `abbr` provenance;
- stable per-cell `headerReferenceIssues` for table-scoped duplicate `th` IDs,
  duplicate reference tokens, invalid IDREF tokens, missing targets, and
  non-header targets;
- unchanged span/scope summaries and deterministic HTML serialization.

The focused regression fixture combines duplicate table-local `th` IDs,
duplicate `headers` tokens, an invalid `bad<tag` IDREF, a missing target, and a
`td` non-header target in one cell to pin issue ordering.

Counter delta after rebase on `179b768e09`:

- `phpPass`: `3426 -> 3427`
- `phpFail`: `0`
- upstream mapped cases: `3380 -> 3381`
- `mappedXmlHtmlDomTableHeaderReferenceCases`: `0 -> 1`
- `xmlHtmlDomTableHeaderReferenceAssertions`: `55`

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> `1` file, `3190` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests` -> `46` files, `79509` assertions, `0` failures
