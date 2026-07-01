# Pandoc CSL strict BibTeX accepted/revised dates - 2026-07-01

## Slice

- Bead: `plib-dzjln`
- Area: citation/bibliography CSL core blocker
- Scope: native PHP CSL/BibTeX handoff under `lanes/pandoc`

## Change

`CitationCslProcessor::fromBibtex()` now preserves BibLaTeX publication-state accepted and revised dates through the strict `BibtexCslParser` path, matching the existing legacy `BibtexCslProcessor` and direct CSL JSON handling.

The strict parser now maps:

- Accepted date aliases: `accepteddate`, `accepted-date`, `dateaccepted`, `date-accepted`
- Accepted split date fields: `acceptedyear`, `acceptedmonth`, `acceptedday`, plus `acceptedendyear`, `acceptedendmonth`, `acceptedendday`
- Accepted time fields: `acceptedhour`, `acceptedminute`, `acceptedsecond`, `acceptedtimezone`, plus accepted end-time fields
- Accepted era fields: `accepteddateera`, `accepted-date-era`, `dateacceptedera`, `date-accepted-era`
- Revised date aliases: `reviseddate`, `revised-date`, `revisiondate`, `revision-date`, `daterevised`, `date-revised`, `revdate`
- Revised split date fields: `revisedyear`, `revisedmonth`, `revisedday`, plus `revisedendyear`, `revisedendmonth`, `revisedendday`
- Revised time fields: `revisedhour`, `revisedminute`, `revisedsecond`, `revisedtimezone`, plus revised end-time fields
- Revised era fields: `reviseddateera`, `revised-date-era`, `revisiondateera`, `revision-date-era`, `daterevisedera`, `date-revised-era`

The new regression test exercises strict BibTeX parsing, normalized CSL item dates, rendered CSL date variables, time/era summaries, alias handling, approximate dates, open-ended revised dates, and WordPress bibliography output.

## Post-rebase validation

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  Result: 1 file, 6,173 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  Result: 3 files, 7,425 assertions, 0 failures.
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- Conflict-marker scan of changed files

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Node tooling, zip/unzip, validators, or live services were invoked.
