# BibLaTeX Legacy Addendum/Note Handoff Slice

Slice: `pandoc-biblatex-legacy-addendum-note-handoff-20260701`

This bounded legacy CSL slice keeps BibLaTeX `addendum` separate from `note`
inside `BibtexCslProcessor` items. The legacy processor already used
`addendum` as a fallback `note`; it now also emits first-class CSL `addendum`
metadata so downstream styles can render `<text variable="note"/>` and
`<text variable="addendum"/>` independently.

The default legacy bibliography text exposes `Addendum:` only when the imported
addendum is distinct from the note fallback, preserving prior addendum-only
fallback behavior while making explicit note/addendum pairs reviewable.

Covered behavior:

- BibLaTeX `note` and `addendum` survive as separate CSL item fields.
- Raw BibLaTeX field provenance still records the original `addendum`.
- `CitationCslProcessor::fromItems()` can render both fields through CSL style
  variables.
- Legacy citation handoff and WordPress bibliography blocks retain the
  addendum metadata.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`

Non-overlap: this does not repeat the accepted Citation-layer BibLaTeX parser
coverage for `note`, `addendum`, and `howpublished`. It only closes the older
`BibtexCslProcessor` legacy handoff path where `addendum` was otherwise folded
into `note`.
