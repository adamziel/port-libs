# Pandoc CSL BibLaTeX Review Entry Type Issue Handoff

Slice: `plib-0b8x6`
Date: 2026-07-01

`@review` entries now reach CSL handoff as CSL `review` items instead of
falling back to generic legacy article metadata. The strict BibTeX parser also
promotes `number` to CSL `issue` for `@review`, matching the legacy
BibLaTeX handoff policy already used for review entries.

The legacy `BibtexCslProcessor` type map was aligned with the strict parser for
`periodical`, `suppperiodical`, and `review`, so older bibliography handoff
fixtures and current `CitationCslProcessor::fromBibtex()` share the same CSL
type routing.

The focused tests cover raw parsed metadata, normalized CSL items, CSL
`type="review"` conditionals, issue rendering, default bibliography text, and
WordPress block output.

No external Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite,
TeX/browser engine, Node tooling, zip/unzip, Jupyter, network service, or
external validator was invoked.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed post-rebase with `2 test files, 7165 assertions, 0 failures`.
