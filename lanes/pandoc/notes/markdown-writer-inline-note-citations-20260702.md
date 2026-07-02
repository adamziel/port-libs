# Markdown writer inline-note citations

Work item: `plib-gvyxg`

## Summary

The Markdown writer now has a fixture-backed completion slice for the upstream
inline-note citation fixture. The mapped cases construct the three writer-side
note shapes directly: a normal citation inside a note, a link-with-title
followed by an author-in-text citation suffix, and a code span before a mixed
normal/suppress-author citation group.

The writer emits stable footnote definitions for each case, and each generated
Markdown packet round-trips through `MarkdownReader` back to the same writer
output while preserving the expected citation ids inside the note body.

## Non-overlap

This slice does not change reader parsing, citeproc, bibliography rendering,
footnote label allocation, or WordPress handoff behavior. It adds bounded
upstream-mapped Markdown writer fixture coverage and manifest accounting only.

## Validation

- `php -l lanes/pandoc/tests/MarkdownWriterInlineNoteCitationFixtureCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterInlineNoteCitationFixtureCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterInlineNoteCitationFixtureCompletionTest.php lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php lanes/pandoc/tests/MarkdownWriterCitationSpanBoundaryFixtureCompletionTest.php lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterAngleAutolinkFixtureCompletionTest.php lanes/pandoc/tests/MarkdownWriterCitationSpanBoundaryFixtureCompletionTest.php lanes/pandoc/tests/MarkdownWriterDetailsSummaryFixtureCompletionTest.php lanes/pandoc/tests/MarkdownWriterFancyListFixtureCompletionTest.php lanes/pandoc/tests/MarkdownWriterInlineNoteCitationFixtureCompletionTest.php lanes/pandoc/tests/MarkdownWriterOmittedDestinationTitleFixtureTest.php lanes/pandoc/tests/MarkdownWriterParseRawFixtureCompletionTest.php lanes/pandoc/tests/MarkdownWriterShortcutReferenceFixtureCompletionTest.php lanes/pandoc/tests/MarkdownWriterTopLevelFixtureCompletionTest.php lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`

An exploratory all-fixture sweep that also included
`MarkdownWriterLineBlockWhitespaceFixtureHarvestTest.php` and
`MarkdownWriterSpacedNestedStrongFixtureTest.php` remained red with 56
pre-existing failures outside this slice. The adjacent fixture subset above
passed with 11 files, 368 assertions, and 0 failures.

No Pandoc binary, office suite, TeX/browser engine, Node tooling, or external
validator was invoked.
