# Independent Audit - 2026-05-24T20:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
`audits/integration-status.md`, and recent Git history through
`1d3c64bf Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 20:10:45Z -> 20:11:19Z
HEAD during audit stability polls: 1d3c64bfaee4
recent history: 1d3c64bf Refresh independent audit status; 958ad536 Integrate markerPDF Tm horizontal scale slice; 4756c15a Record esbuild handoff rejection; 25c16daf Refresh independent audit status; d8bada7c Record Readability handoff rejection
tracked status rows: 234 -> 234
default status rows including untracked: 22717 -> 22743
dirty shortstat: 234 files changed, 199408 insertions(+), 23625 deletions(-) -> 234 files changed, 199414 insertions(+), 23626 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
root run by this audit: not started; exact no-argument root gate was clear, but the checkout moved between stability polls
```

Required exact pre-root process gate:

```text
2026-05-24T20:10:45Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T20:11:19Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
empty, but the dirty aggregate was not stable enough: untracked/default status
rows and shortstat changed during the root gate window. A root result from this
state would not be a defensible acceptance baseline.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1130/1270                 3744/0
dolt          613/613                   458/0
esbuild       482/2567                  482/0
gitoxide      1468/2877                 7588/0
libsqlite     216/1589                  216/0
LightningCSS  3008/3548                 4379/0
markerPDF     165/78                    270/0
pandoc        2276/2276                 400/0
quadrable     55/55                     259/0
rclone        480/1601                  474/0
Readability   1563/1984                 146/0
syncthing     658/658                   9131/0
```

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-51`, `audits/integration-status.md:1-75`,
     `lanes/*/lane-status.json`, `tools/run-tests.php`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-36`, and
     `goal.md:48` require small reviewable slices, meaningful coverage,
     cleanup, commits, and verified handoff before assigning the next slice.
   - Evidence: `HEAD` was stable at `1d3c64bfaee4` during my two short
     samples, but the default status row count moved `22717 -> 22743` and
     dirty shortstat moved from `234 files changed, 199408 insertions(+),
     23625 deletions(-)` to `234 files changed, 199414 insertions(+),
     23626 deletions(-)`. That is still active work, not a frozen review
     point.

2. **Critical - no root-harness evidence is valid for the current dirty snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:1-75`,
     `lanes/*/lane-status.json`, `progress.md:51`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no rows at
     `20:10:45Z` and `20:11:19Z`, but the checkout changed between those
     samples, so I did not start a root run. The integration-owned root pass
     recorded for `958ad536` is explicitly scoped to the held markerPDF Tm
     horizontal-scale slice and cannot be reused for the current aggregate,
     which now includes newer pending markerPDF ASCIIHex work plus other lane
     changes.

3. **Critical - `porting.html` and `porting-summary.json` remain materially stale.**
   - Paths: `porting.html:34-38`, `porting.html:56-67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to show current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit.
   - Evidence: the dashboard still publishes source snapshot
     `84a9d33d56d3`, generated `2026-05-24 19:49:49 UTC`, while current
     `HEAD` is `1d3c64bfaee4`. Dashboard rows are stale against current
     metadata: Difftastic shows `240/586` mapped and `240` PHP passes while
     current metadata is `1130/1270` and `3744`; LightningCSS shows
     `886/3532` and `1037` while current metadata is `3008/3548` and `4379`;
     markerPDF shows `163/78` and `268` while current metadata is `165/78`
     and `270`; rclone shows denominator `2553` and `458` mapped while the
     current manifest is `480/1601`; Syncthing shows `324` PHP passes while
     current status is `9131`.

4. **High - support-library coverage is visible but still not first-class accepted work.**
   - Paths: `dependency-backlog.json:7-687`, `porting.html:72-129`,
     `progress.md:17-35`, `lanes/rclone/lane-status.json:9-14`,
     `lanes/syncthing/lane-status.json:5-14`.
   - Goal requirement at risk: `goal.md:35-40` plus the latest support-library
     directives require bounded native components, activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail ledgers, malformed/corrupt cases where relevant, and bounded
     install-attempt notes before dependency progress credit.
   - Evidence: the backlog has 37 rows and 0 active support ports. Pandoc's
     required DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression needs are visible as gated rows.
     But none has an accepted support manifest, dependency-specific PHP
     ledger, malformed/corrupt evidence record, activation record, or bounded
     install-attempt note. Rclone is already accumulating WebDAV DELETE,
     MKCOL, and PUT lane-local slices while `webdav-protocol-core` remains inactive;
     Syncthing status includes `/qr/` route-body coverage while
     `qr-code-matrix-core` remains blocked. Those claims must stay lane-local
     and uncredited as reusable support-library progress until accepted gates
     and denominators exist.

5. **High - Pandoc status overstates readiness for the original document-conversion goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:424-426`,
     `lanes/pandoc/lane-status.json:4-14`,
     `dependency-backlog.json:7-424`, `dependency-backlog.json:629-644`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus Markdown, HTML, WXR, EPUB/PDF-oriented
     intermediate forms, and WordPress block output; `goal.md:35-40` requires
     real denominators, edge-case/error coverage, and explicit blockers.
   - Evidence: the manifest reports `mapped: 2276` of `total: 2276` and the
     lane status reports `estimatedProgress: 99`, but current PHP evidence is
     only `400` focused behavior tests. The full Haskell runner remains
     unexecuted, and the status itself leaves live fetching/openURL, broader
     HTML parser parity, TeX/PlainMath/MathML, malformed HTML, PDF/package
     parsing, citation/CSL, ZIP/XML/HTML, Unicode/charset, and syntax
     highlighting behind future gates.

6. **High - markerPDF still has a non-normalized denominator and a new pending PDF slice beyond the accepted root evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14-16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:223-224`,
     `lanes/markerpdf/lane-status.json:4-14`,
     `dependency-backlog.json:272-337`.
   - Goal requirement at risk: `goal.md:30` and `goal.md:35-40` say
     wrappers, shell-outs, whole applications, runtime launchers, and plan-only
     behavior must not count as native implementation progress.
   - Evidence: current markerPDF metadata reports `mapped: 165` against
     `total: 78`, mixing source-path inventory units with focused semantic
     mappings. After the accepted `958ad536` Tm horizontal-scale slice, the
     lane status has already moved to a pending ASCIIHexDecode stream-filter
     handoff with `phpPass: 270` and no root run. The status still lists full
     benchmark/app parity blockers around Streamlit, FastAPI/Uvicorn,
     pypdfium/PDF rendering, Surya/OCR/Texify/Torch/Nougat, batch/chunk
     scripts, and model/runtime execution. Those remain blockers or
     supplied-result boundaries, not progress.

7. **High - near-complete lane percentages are mostly pending or uncommitted handoffs.**
   - Paths: `lanes/difftastic/lane-status.json:4-14`,
     `lanes/dolt/lane-status.json:4-14`,
     `lanes/esbuild/lane-status.json:4-14`,
     `lanes/gitoxide/lane-status.json:4-14`,
     `lanes/libsqlite/lane-status.json:4-14`,
     `lanes/lightningcss/lane-status.json:4-14`,
     `lanes/pandoc/lane-status.json:4-14`,
     `lanes/quadrable/lane-status.json:4-14`,
     `lanes/rclone/lane-status.json:4-14`,
     `lanes/readability/lane-status.json:4-14`,
     `lanes/syncthing/lane-status.json:4-14`,
     `audits/integration-status.md:76-220`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and `goal.md:48`
     require focused reviewable slices with passing tests, commits, cleanup,
     and new assignment only after verification.
   - Evidence: most lanes advertise `95-99%` progress or green focused tests,
     but their `latestCommit` fields are `pending`, `uncommitted`, `not
     committed`, or broad prose batches. The latest esbuild status remains
     `95%` even though the recent esbuild handoff was rejected for dirty-scope
     mismatch and the current `latestCommit` still describes a broad
     accumulated metafile/source-map/data-URL/TypeScript analyzer batch.

8. **Medium - manifest/status count units are still inconsistent across lanes.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14-16`,
     `lanes/markerpdf/lane-status.json:5-10`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14-15`,
     `lanes/rclone/lane-status.json:5-10`,
     `porting.html:62-65`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require durable coordination by upstream denominator, mapped tests, and
     PHP pass/fail counts.
   - Evidence: markerPDF maps `165` items against a `78`-path denominator,
     while rclone's current manifest uses a `1601` Go test-function
     denominator with `480` mapped, status reports `474` PHP behavior tests,
     and the dashboard still shows the older `2553` repository-file
     denominator. These are different unit systems presented as one progress
     signal.

## Required Next Intervention

Freeze writers/runners/status publishers long enough for two stable polls of
`HEAD`, tracked/default status rows, dirty shortstat, manifest/status mtimes,
and the exact root process gate. Do not broaden lane work while pending
handoffs remain accumulated. If any exact no-argument root PID appears, wait
it out and do not start a duplicate. Next, accept or reject one owner-free
reduced lane batch whose dirty files exactly match its evidence, normalize that
lane's manifest/status count units, regenerate dashboard artifacts from the
accepted commit, then run one serialized no-argument `php tools/run-tests.php`
only if `pgrep -af '^php tools/run-tests\.php$'` remains empty on the frozen
snapshot. Keep support-library rows inactive until a base lane is
accepted-ready or accepted-blocked on the exact bounded component with its own
denominator, mapped fixtures, malformed/corrupt cases, PHP pass/fail ledger,
and bounded install-attempt notes where missing packages matter.
