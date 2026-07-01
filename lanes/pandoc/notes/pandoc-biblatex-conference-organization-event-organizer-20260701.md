# BibLaTeX conference organization event organizer

## Scope

This slice aligns legacy `BibtexCslProcessor` handoff with the direct
`BibtexCslParser` path for conference-like entries. For `conference`,
`inproceedings`, and `proceedings` records, `organization` is promoted to the
CSL `event-organizer` name variable when no explicit `eventorganizer` or
`organizer` field is present.

## Coverage

- Direct parser and legacy processor paths now agree on organization-backed
  event organizer names for conference proceedings records.
- `organization+an` name annotations survive the fallback and remain visible in
  `CitationCslProcessor` normalized items, CSL style rendering, citation
  handoff, and WordPress bibliography output.
- Non-conference `organization` remains publisher metadata and is not promoted
  to an event organizer.

## Accounting

- `legacyBiblatexConferenceOrganizationEventOrganizerCases`: 1
- `mappedLegacyBiblatexConferenceOrganizationEventOrganizerCases`: 1
- `legacyBiblatexConferenceOrganizationEventOrganizerAssertions`: 27

The mapped denominator increases from 2317 to 2318 on the CSL integration
branch.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorConferenceOrganizationEventOrganizerTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorConferenceOrganizationEventOrganizerTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`

No external citeproc, BibTeX, Biber, Pandoc, browser, TeX, office suite,
Node tooling, or validator was invoked for this slice.
