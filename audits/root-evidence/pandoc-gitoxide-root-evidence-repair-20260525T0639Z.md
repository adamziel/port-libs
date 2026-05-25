# Pandoc and Gitoxide Root Evidence Repair

Date: 2026-05-25T06:39Z

Scope: audit-only evidence correction for the bounded lane-local micro-slices accepted at:

- Pandoc: `f2ef60747cd1` (`f2ef60747cd1` exact commit tested)
- Gitoxide: `5e2154cc92e8` (`5e2154cc92e8` exact commit tested)

Clean temporary worktrees:

- Pandoc: `/tmp/port-libs-root-evidence-pandoc-f2ef60747cd1`
- Gitoxide: `/tmp/port-libs-root-evidence-gitoxide-5e2154cc92e8`

Commands and results:

- Pandoc worktree:
  - `php tools/run-tests.php`
  - Result: `213 test files, 25629 assertions, 0 failures`
  - `git diff --check`
  - Result: exit 0, no output
- Gitoxide worktree:
  - `php tools/run-tests.php`
  - Result: `213 test files, 25636 assertions, 0 failures`
  - `git diff --check`
  - Result: exit 0, no output

No live-service provider tests were run. The root harnesses were run with no arguments only, serialized, after checking that no `php tools/run-tests.php` process was active before each root run.

No source changes were made for this correction. This commit records audit evidence only.
