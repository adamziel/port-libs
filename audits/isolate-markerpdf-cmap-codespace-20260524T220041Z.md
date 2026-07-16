# Isolated markerPDF CMap Codespace Slice

- Worktree: `/home/claude/port-libs/.upstream-cache/slice-isolation/markerpdf-cmap-codespace-20260524T220041Z`
- Base commit: `cd6a6b6997a20283b1797b2c4bcb157fa76ccf1a`
- Patch: `.tmux-team/tmp/isolate-markerpdf-cmap-codespace-20260524T220041Z.patch`
- Ready marker: `.tmux-team/tmp/handoff-candidates/port-isolate-markerpdf-cmap-codespace.ready`

## Scope

Rebuilt only the markerPDF searchable text extraction slice for `/ToUnicode` CMap `begincodespacerange` variable-width fallback alignment. The patch adds native CMap discovery/parsing in `PdfTextExtractor`, applies the active `Tf` font resource while decoding text operands, uses CMap code-space ranges to consume unmapped fallback source codes at the declared one-byte or two-byte width, and adds a WordPress example that emits `WordPress Blocks` from a mixed-width CMap.

Touched files in the clean detached worktree:

- `lanes/markerpdf/src/PdfTextExtractor.php`
- `lanes/markerpdf/tests/PdfTextExtractorTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-import.php`

No JSON files were touched, so `jq empty` was not applicable.

## Excluded Dirty-Main Changes

The dirty main checkout was read only as reference. This isolated patch excludes the unrelated dirty markerPDF changes present in main, including:

- `RunLengthDecode` stream-filter decoding.
- `ASCII85Decode` stream-filter decoding.
- `/WinAnsiEncoding` simple-font decoding.
- simple-font `/Differences` glyph-name decoding.
- unrelated markerPDF manifest/status/notes count expansions.
- unrelated markerPDF examples for RunLength, ASCII85, WinAnsi, Differences, fallback-only CMap, OCR/model/table/layout/runtime behavior.

The patch also does not activate a shared support-library row. It reuses the existing inactive `pdf-text-dictionary-core` candidate boundary as lane-local context only.

## Verification

All commands below were run from the clean detached worktree unless noted.

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` exited `0`.
  - Output: `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` exited `0`.
  - Output: `No syntax errors detected in lanes/markerpdf/tests/PdfTextExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-import.php` exited `0`.
  - Output: `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-import.php`
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-import.php` exited `0`.
  - Output included `WordPress Blocks`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` exited `0`.
  - Result: `1 test files, 26 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests` exited `0`.
  - Result: `47 test files, 959 assertions, 0 failures`.
- `git diff --check -- lanes/markerpdf` exited `0`.
- `git diff --name-only -- lanes/markerpdf | rg '\.json$' || true` exited `0` with no output; no touched JSON files required `jq empty`.
- `git diff --binary -- lanes/markerpdf > /home/claude/port-libs/.tmux-team/tmp/isolate-markerpdf-cmap-codespace-20260524T220041Z.patch` exited `0`.

## Decision

Ready marker created because the patch is clean, lane-bounded, and verified. Integrator should accept this isolated split if it still applies to the current integration base.
