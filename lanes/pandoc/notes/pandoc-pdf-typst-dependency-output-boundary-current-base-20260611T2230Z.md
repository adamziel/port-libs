# pandoc-pdf-typst-dependency-output-boundary-current-base-20260611T2230Z

Slice: `plib-va3pk`, PDF/Typst boundary provenance.

Base: current `origin/main` `e4b151737ee24e60827e641ea3d7a6ebc8c5043a`.

`PdfEngineHandoff` now preserves Typst dependency sidecar output path boundary
provenance for `--deps` and `--make-deps`. Plans retain the selected sidecar
path, unsafe URI/absolute path diagnostics, override history, and dependency
format metadata in one inert `typstBoundaryProvenance.dependencyOutput` packet.
Unsafe dependency sidecar targets remain review metadata only and are not added
to expected local engine artifacts.

This does not repeat the accepted dependency-output target policy or
`--deps-format` provenance slices. This slice owns the option-path boundary
before any fake-run dependency file is available.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1835 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66649 assertions, 0 failures`

Parity accounting: one new focused PHP PASS case; lane `phpPass` moves
`3133 -> 3134` by note.
