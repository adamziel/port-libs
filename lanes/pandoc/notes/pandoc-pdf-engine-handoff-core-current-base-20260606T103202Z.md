# Pandoc PDF Engine Handoff Core Current Base

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T103202Z`
Base accepted HEAD: `aafdefee09bf90e527df1bcd5b451a92fb989b76`

## Scope

Added bounded native produced-PDF annotation review-state handoff. `PdfEngineHandoff` now preserves `/IRT`, `/RT`, `/State`, and `/StateModel` from annotation dictionaries as `replyTo`, `replyType`, `state`, and `stateModel`, and emits `pdf-byte-annotation-replies:N` plus `pdf-byte-annotation-review-states:N` diagnostics when present.

This stays inside the fake-runner PDF-byte inspection contract. It does not implement, invoke, or shell out to Pandoc, TeX/PDF engines, Typst, browser renderers, roff, JavaScript, external PDF validators, online sanitizers, or online services.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` failed with `1 test files, 613 assertions, 1 failures` before the implementation preserved reply/review-state fields.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 622 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed.
- Focused delta: `+1` PDF engine handoff PHP PASS case, `+9` net focused assertions, mapped PDF engine handoff cases `10 -> 11`, mapped inventory `1712 -> 1713`, lane `phpPass` `1298 -> 1299`.

## Non-Overlap

Avoided accepted/current PDF engine surfaces for sidecar/log fake-runner planning, SyncTeX, recorder/transcript metadata, XMP/PDF-A, tagged PDF metadata, catalog URI base, page display metadata, RichMedia, signatures, forms, and external renderer parity. This slice only adds bounded annotation reply/review-state metadata already present in produced PDF bytes.

## Dependency Closure

No new support component is needed. The slice reuses `PdfEngineHandoff` PDF dictionary parsing, the existing fake-runner file-map/result contract, and the existing WordPress PDF engine example. Upstream runner parity remains blocked on a hydrated Pandoc checkout plus Cabal project/package files and Haskell Tasty executable builds for `test-pandoc` and `test-pandoc-lua-engine`.
