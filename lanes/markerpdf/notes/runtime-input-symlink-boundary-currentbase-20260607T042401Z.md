# markerpdf runtime input symlink boundary current-base slice

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260607T042401Z`

Accepted base: `b82f9244c643b3e715f941cde65b2e86a2a3ee98`

Source truth:

- Upstream `sddai/markerPDF` `convert.py` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` computes `in_folder = os.path.abspath(args.in_folder)`, builds `files = [os.path.join(in_folder, f) for f in os.listdir(in_folder)]`, filters with `os.path.isfile(f)`, and only then creates the output folder, chunks files, loads metadata, prepares model handoff, and constructs task args.

Implemented boundary:

- `BatchConverter::runtimeMainPreflightPlan()` now records when the input folder argument is a symlinked directory.
- The native plan records that `os.path.abspath` preserves the symlink path, `os.listdir` follows the directory symlink, `realpath` differs from the absolute argument path, and task filepaths preserve the symlink-prefix path before metadata lookup and worker pool handoff.
- No Python, CUDA, Surya/Texify/Torch model execution, multiprocessing, PDF rendering, or external PDF tools are executed.

Focused evidence:

- Red-first before source edit: `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` failed on missing `input_folder_is_symlink` review metadata after `1222` assertions.
- After source edit: `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` passed with `1 test files, 1246 assertions, 0 failures`.

WordPress smoke:

- Added `lanes/markerpdf/examples/wordpress-marker-runtime-input-symlink-boundary-currentbase.php`.
- The smoke covers WordPress upload directories mounted through a symlink, preserving task paths under the symlinked upload root while still queueing regular non-PDF sidecars before worker-side preflight.

Dependency closure:

- No new support component is needed. This slice reuses the existing native PHP `BatchConverter`, filesystem path review helpers, file listing, metadata lookup, and task-arg review boundaries.
- Remaining model/OCR behavior stays intentionally out of scope under the current no-GPU markerPDF directive.
