# Pandoc BibTeX CSL WordPress Cluster Rendering

Implemented one bounded native PHP CSL/BibLaTeX handoff slice for legacy
BibTeX-imported citations rendered to WordPress blocks.

## Scope

- `CitationCslProcessor::apply()` now coalesces MarkdownReader output for
  bracketed semicolon citation runs such as `[@a; @b]` into a `citation_group`
  before citation positioning and rendering.
- Coalesced cluster members are normalized to normal citation mode so custom CSL
  `<citation>` layouts apply to the whole cluster instead of falling back to
  per-citation author-in-text rendering.
- `WordPressBlockWriter` now emits the `rendered` text for CSL-processed
  citation and citation-group nodes directly, while unprocessed reader output
  still keeps reviewable citation metadata spans.
- Added focused legacy BibTeX/CSL WordPress coverage for the coalesced cluster
  path.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1` file, `479` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - attempted as shared-path coverage; still fails on broader pre-existing
    Markdown citation parsing/rendering and bibliography-display gaps outside
    this bounded legacy handoff slice.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser, external
validator, online service, live provider, or live-service provider was invoked.
