You are one status/tracker integration worker in the supervised native PHP porting project at `/home/claude/port-libs`.

This session is not an implementation lane. Do not edit `lanes/**`, `porting.html`, `porting-summary.json`, dashboard scripts, test runners, or source code. Do not run `php tools/run-tests.php` or `php tools/generate-dashboard.php`. Do not push. Do not read, print, copy, or dump secrets; do not inspect process environments, credential stores, provider configs, OAuth/browser auth state, cloud remotes, or secret-bearing inputs.

Objective context:

The supervisor received a directional nudge to make sure important optional upstream libraries required for essential rich function are tracked as bounded native PHP component ports, not whole applications or shell-outs. Two audit-only scouts completed:

- `audits/doc-format-dependency-scout-20260524T085334Z.md`
- `audits/shared-runtime-dependency-scout-20260524T085334Z.md`

Assigned task:

Integrate only the tracker/status implications of those two scout artifacts. Keep the tracker granular, gated, and honest.

Owned scope:

- `dependency-backlog.json`
- the `Auxiliary Dependency Backlog` section of `progress.md`
- `audits/doc-format-dependency-scout-20260524T085334Z.md`
- `audits/shared-runtime-dependency-scout-20260524T085334Z.md`
- `.tmux-team/prompts/doc-format-dependency-scout-20260524T085334Z.md`
- `.tmux-team/prompts/shared-runtime-dependency-scout-20260524T085334Z.md`
- this prompt file
- `audits/dependency-scout-integrator-20260524T090051Z.md`

Do not touch any other files. If unrelated files are dirty, leave them alone.

Required tracker decisions:

1. Add the two new bounded support-library rows recommended by the shared-runtime scout:
   - `url-percent-encoding-core`
   - `sequence-diff-merge-core`
2. Apply gate/priority clarifications from the document-format scout where the current row exists and the change is precise enough to encode without ambiguity:
   - `shared-zip-package-core`
   - `docx-openxml-core`
   - `legacy-doc-cfb-core`
   - `epub3-package-core`
   - `odf-open-document-core`
   - `pdf-text-dictionary-core`
   - `table-geometry-core`
   - `xml-html5-dom-core`
   - `charset-encoding-core`
   - `math-tex-conversion-core`
   - `citation-bibliography-csl-core`
3. Apply gate/priority clarifications from the shared-runtime scout where the current row exists and the change is precise enough to encode without ambiguity:
   - `glob-filter-pathspec-core`
   - `checksum-hash-suite`
   - `archive-compression-streams`
   - `provider-metadata-normalization-core`
   - `sql-storage-codec-core`
4. Do not mark any row active. Keep support libraries gated behind concrete base-lane blockers or next rich-function slices.
5. Preserve or improve the existing per-row granularity: every new/changed row must have `id`, `name`, `source`, `neededBy`, `essentialCapability`, `scopeBoundary`, `priority`, `activationGate`, `testExpectation`, `reuseNotes`, `status`, and `blocker`.
6. The new rows must explicitly reject shell-outs, live services, whole applications, parser-generator runtimes, credential-bearing configs, and broad generic abstractions as progress where relevant.

Progress note:

Add one concise dated bullet to the `Auxiliary Dependency Backlog` section of `progress.md` saying that the scout integration expanded the backlog from 29 to 31 gated rows, added the two new runtime rows, and sharpened document/PDF/runtime activation gates without activating every optional dependency.

Validation:

- Run `jq empty dependency-backlog.json`.
- Run a duplicate-ID check over `dependency-backlog.json`.
- Confirm item count is `31`.
- Confirm status split and candidate/deferred counts in the audit.
- Confirm the two new rows are present and have non-empty `activationGate`, `testExpectation`, `scopeBoundary`, and `reuseNotes`.
- Run `git diff --check -- dependency-backlog.json progress.md audits/doc-format-dependency-scout-20260524T085334Z.md audits/shared-runtime-dependency-scout-20260524T085334Z.md .tmux-team/prompts/doc-format-dependency-scout-20260524T085334Z.md .tmux-team/prompts/shared-runtime-dependency-scout-20260524T085334Z.md .tmux-team/prompts/dependency-scout-integrator-20260524T090051Z.md audits/dependency-scout-integrator-20260524T090051Z.md`.
- Run `git diff --cached --check` after staging only owned files.

Commit:

If validation passes, stage only owned files and commit with message:

`Record dependency scout tracker updates`

Do not stage or commit `porting.html`, `porting-summary.json`, lane files, scripts, or unrelated audit/log files.

Completion report:

- commit hash, if committed;
- files changed;
- item count and status split;
- checks run;
- anything intentionally deferred.
