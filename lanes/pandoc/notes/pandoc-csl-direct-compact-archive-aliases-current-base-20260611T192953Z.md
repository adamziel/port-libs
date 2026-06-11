# Pandoc CSL direct compact archive aliases

Bead: plib-fsq09
Slice: Pandoc citation/bibliography CSL core blocker 20260611T192953Z
Base: origin/main 6673f4a17

## Change

- Normalized direct CSL JSON compact archive/eprint aliases into the existing archive review fields:
  `archiveprefix`, `archivecollection`, `archiveplace`, `archivelocation`,
  `eprinttype`, `eprintclass`, `eprint`, and compact summary aliases.
- Kept the existing bounded archive summary synthesis path, so direct compact
  inputs render through CSL variables and default bibliography text without
  external citeproc handoff.
- Added focused coverage for compact and camelCase direct eprint aliases, compact
  archive aliases, explicit compact summary aliases, CSL style rendering, and
  WordPress bibliography handoff.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 file, 4795 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 65763 assertions, 0 failures
