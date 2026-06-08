# Runtime Output Document Basename Boundary Current Base

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::process_single_pdf` derives `fname = os.path.basename(filepath)` before `marker.output.save_markdown(out_folder, fname, ...)`.
- Upstream `convert.py::main` also keys metadata by `metadata.get(os.path.basename(f))`, so batch runtime tasks are basename-oriented before model workers launch.
- Upstream `convert_single.py::main` converts the raw filename, then calls `fname = os.path.basename(fname)` before `save_markdown(args.output, fname, ...)`.

## Implementation

- `OutputWriter::getSubfolderPath()` and `getMarkdownFilepath()` now apply the same document basename boundary before stripping the final extension and joining output paths.
- `saveMarkdownArtifactBoundary()` now includes `document_filename_boundary` review metadata with the raw filename, native safe basename/stem, path-segment removal status, and no-execution flags.
- Empty document basenames remain supported for the accepted `convert_single.py` trailing-separator boundary.

## Verification

- `php -l lanes/markerpdf/src/OutputWriter.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/OutputRuntimeDocumentBasenameBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-output-document-basename-boundary-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/OutputRuntimeDocumentBasenameBoundaryCurrentBaseTest.php` => 1 test file / 27 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/OutputWriterTest.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php lanes/markerpdf/tests/OutputMarkdownTableImageArtifactCurrentBaseTest.php lanes/markerpdf/tests/OutputRuntimeDocumentBasenameBoundaryCurrentBaseTest.php` => 5 test files / 308 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-marker-runtime-output-document-basename-boundary-currentbase.php` => exits 0 and reports `path_segments_removed_for_native_output_paths=true`, `subfolder_prefixed_by_output_folder=true`, `outside_output_path_exists=false`, and no Streamlit/PDFium/Python/models/external PDF tools.

## Non-Overlap

This does not repeat accepted runtime pool/model handoff, worker initializer, empty queue/model-list, metadata-file, markdown-exists path, single trailing basename, server upload filename, or image artifact filename sanitization slices. The new boundary is only direct `marker.output.save_markdown` document filename basename handling for output subfolder and Markdown artifact paths.

## Dependency Closure

No new support component is needed. This reuses the existing native `OutputWriter` artifact writer and stays inside the no-GPU/no-model runtime preflight boundary.
