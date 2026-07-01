# CSL MathSciNet ID Alias Follow-Up

`plib-pde8d` already normalized the core `mathscinet` alias into CSL
`MRNumber` / `mrNumber` registry metadata. This follow-up extends the same
native PHP path to compact ID spellings:

- BibLaTeX `mathscinet-id` / `mathscinetid` fields now populate `MRNumber`
  while preserving raw field provenance.
- Direct CSL item keys `mathscinetId`, `mathscinet-id`, `mathscinetid`,
  `mathSciNet`, and `mathSciNetId` normalize to `mrNumber`.
- CSL text variables `mathscinet-id` and `mathscinetid` render the normalized
  MR identifier alongside the existing `mathscinet`, `mrnumber`, and
  `mr-number` variables.

The focused `CitationCslProcessorTest.php` fixture keeps bibliography and
WordPress handoff coverage in the existing MathSciNet alias case.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.
