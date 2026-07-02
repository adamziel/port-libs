# XML/HTML5 DOM DocBook media issue review

This slice adds compact reviewer metadata for DocBook media objects in
`XmlHtmlDom::summarizeHtmlFragment()`:

- `docBookMediaReviewPolicy` identifies the bounded media-object issue review.
- `docBookMediaIssueCodes` and `docBookMediaIssueCodeCount` roll detailed media
  issue records into stable code summaries.
- `docBookMediaValid` exposes the no-issue state for importer gates while
  preserving existing detailed `docBookMediaIssues` records.

The covered diagnostics remain metadata-only: missing media alt text, missing
`imagedata` targets, invalid `linkend` IDs, and unresolved `linkend` targets.
No image bytes are read, no DocBook reader parity is claimed, and no external
Pandoc, browser engine, network fetch, Node tooling, office suite, TeX engine,
zip/unzip tool, or external validator is invoked.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomDocBookMediaIssueReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomDocBookMediaIssueReviewTest.php`
  - Result: 1 file, 31 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomDocBookMediaIssueReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: 2 files, 6,255 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - Result: 78 files, 9,581 assertions, 0 failures.

## Mapping Delta

This does not repeat existing DocBook structure packet, media target manifest,
caption cross-reference, inline media alt text, HTML media, image loading,
canvas, MathML/SVG, or general XML namespace work. The change is limited to
compact issue-code and validity rollups for existing DocBook media object
diagnostics in the XML/HTML DOM summary layer. Direct-format parity remains
tracked separately; this is native PHP review metadata only.
