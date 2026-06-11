# pandoc-pdf-typst-warning-source-provenance-current-base-20260611T182923Z

Slice: `plib-mge6r`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` fake-runner review metadata for
Typst diagnostics. It now extracts structured warning provenance from inert
stdout/stderr/log text:

- warning message text;
- source path plus line/column and optional end line/column spans;
- Typst hint/help lines;
- declared `--root` boundary state for warning source paths;
- outside-root, external-source, and missing-source issue markers.

The provenance is exposed on `fakeRun()` as `typstWarningProvenance`, mirrored
inside `artifactProvenanceReview`, and carried through `fakeRunSequence()` as
`finalTypstWarningProvenance`.

Accounting:

- `phpPass`: `3102 -> 3103`.
- Static mapped denominator: `3203 -> 3204`.
- `mappedTypstWarningSourceProvenanceCases`: `1`.
- `typstWarningSourceProvenanceAssertions`: `8`.
- Focused PDF handoff assertions: `1763 -> 1771`.

Verification on current `origin/main` `16f638244d95`:

- Red-first focused observation: the new `PdfEngineHandoffTest.php` case first
  failed on missing `typstWarningProvenance`.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1
  test files, 1771 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 65437
  assertions, 0 failures`.

No Pandoc, Typst, TeX/PDF engines, browser renderers, external PDF validators,
office suites, zip/unzip, Node tooling, Cabal/Haskell runners, online services,
live provider tests, or live-service provider tests were run.
