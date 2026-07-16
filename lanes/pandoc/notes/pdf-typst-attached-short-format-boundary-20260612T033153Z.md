# Pandoc PDF/Typst attached short format boundary - 20260612T033153Z

Bead: plib-ui77b
Base: origin/main e4b9aebb64

Focused verification:
`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
Result: 1 test file, 2043 assertions, 0 failures.

Full verification:
`php tools/run-tests.php lanes/pandoc/tests`
Result: 44 test files, 70077 assertions, 0 failures.

Mapped one native `PdfEngineHandoff` Typst output-format boundary case. The handoff now treats attached short `-f...` values, such as `-fsvg`, as explicit Typst output-format requests so review packets preserve conflicting format history before the final selected format.

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.
