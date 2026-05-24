# Independent Audit - 2026-05-24T20:06Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
`audits/integration-status.md`, and recent Git history through
`958ad536 Integrate markerPDF Tm horizontal scale slice`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 20:00-20:06
HEAD moved during this audit: 4756c15a0453 -> 958ad536ba58
recent history: 958ad536 Integrate markerPDF Tm horizontal scale slice; 4756c15a Record esbuild handoff rejection; 25c16daf Refresh independent audit status; d8bada7c Record Readability handoff rejection; 353fefd8 Refresh markerPDF integration status; 84a9d33d Refresh independent audit status
tracked status rows moved: 228 -> 228 -> 229 -> 229 -> 229 -> 234
default status rows including untracked moved: 22437 -> 22450 -> 22477 -> 22547 -> 22559 -> 22591
dirty shortstat moved: 228 files changed, 197180 insertions(+), 24007 deletions(-) -> 228 files changed, 197195 insertions(+), 24007 deletions(-) -> 229 files changed, 196997 insertions(+), 23622 deletions(-) -> 229 files changed, 197587 insertions(+), 23760 deletions(-) -> 229 files changed, 197591 insertions(+), 23766 deletions(-) -> 234 files changed, 197761 insertions(+), 23769 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
root run by this audit: not started; active non-audit no-argument root PID 1437381 observed after the tree moved, later cleared while the tree kept moving
```

Required exact pre-root process gate:

```text
2026-05-24T20:00:28Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T20:00:58Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T20:01:20Z pgrep -af '^php tools/run-tests\.php$': 1437381 php tools/run-tests.php
2026-05-24T20:01:20Z owner evidence: 1437381 claude 1413080 Rs 00:12 php tools/run-tests.php
2026-05-24T20:01:43Z owner evidence: 1437381 claude 1413080 Rs 00:31 php tools/run-tests.php
2026-05-24T20:02:07Z owner evidence: 1437381 claude 1413080 Rs 00:55 php tools/run-tests.php
2026-05-24T20:04:56Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T20:06:22Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
clear early, but the checkout was not stable enough. A non-audit root harness
then appeared as PID `1437381`, owned by `claude`, so starting a duplicate was
not allowed. The gate later cleared, but `HEAD`, status rows, and shortstat had
already moved again.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1118/1258                 3729/0
dolt          613/613                   458/0
esbuild       482/2567                  482/0
gitoxide      1467/2877                 7578/0
libsqlite     216/1589                  216/0
LightningCSS  3006/3548                 4376/0
markerPDF     164/78                    269/0
pandoc        2276/2276                 400/0
quadrable     55/55                     259/0
rclone        474/1601                  474/0
Readability   1563/1984                 146/0
syncthing     658/658                   9131/0
```

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-51`, `audits/integration-status.md:3-75`,
     `lanes/*/lane-status.json`, `tools/run-tests.php`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices,
     meaningful coverage, cleanup, commits, and honest repo-wide verification.
   - Evidence: during this audit `HEAD` moved from `4756c15a0453` to
     `958ad536ba58`, tracked rows moved `228 -> 228 -> 229 -> 229 -> 229
     -> 234`, default status rows moved
     `22437 -> 22450 -> 22477 -> 22547 -> 22559 -> 22591`, and dirty
     shortstat moved to `234 files changed, 197761 insertions(+), 23769
     deletions(-)`. Recent history is still rejection/status led with
     one accepted markerPDF slice; most lane metadata still points at pending
     or uncommitted batches.

2. **Critical - no root-harness evidence is valid for the current dirty snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:159-217`,
     `lanes/*/lane-status.json`, `progress.md:51`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording.
   - Evidence: the audit-owned gate was initially clear, but the tree was
     already moving. A non-audit no-argument root harness then appeared as
     PID `1437381 php tools/run-tests.php`, owned by `claude`; no duplicate
     was started. The gate later cleared, but `HEAD` and the dirty aggregate
     had moved again. The accepted markerPDF root results in integration
     status are scoped to held markerPDF slices only and are not reusable for
     the broader dirty aggregate.

3. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:34-38`, `porting.html:56-67`,
     `porting-summary.json:2-3`, `porting-summary.json:11-212`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track current denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes generated time
     `2026-05-24 19:49:49 UTC` from source commit `84a9d33d56d3`, while
     current `HEAD` is `958ad536ba58`. Dashboard rows lag current manifests:
     Difftastic is shown as `240/586` with `240` PHP passes but current
     metadata is `1118/1258` and `3729`; Gitoxide is shown as `1432/2877`
     and `2646` PHP passes but current metadata is `1467/2877` and `7578`;
     rclone is shown as denominator `2553` and `458` mapped but current
     metadata is `1601` and `474`; Syncthing is shown with `324` PHP passes
     while current status is `9126`.

4. **High - the latest esbuild handoff was correctly rejected, but the lane status still advertises the rejected aggregate as 95% progress.**
   - Paths: `audits/integration-status.md:3-75`,
     `lanes/esbuild/lane-status.json:4-13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small reviewable slices whose dirty scope matches
     their evidence before integration.
   - Evidence: integration rejected the esbuild handoff because the claimed
     no-substitution template-literal analyzer slice sat inside a broad
     accumulated dirty patch containing unaccepted metafile/source-map/data-URL,
     TypeScript, phase-import, top-level-await, JSON, numeric, and fixture
     work. The lane status has already grown again to include dynamic import
     trailing-comma metafile records and now reports `phpPass: 482`, plus a
     long uncommitted batch as `latestCommit`, so it should not be
     treated as accepted progress despite the newer markerPDF integration
     commit.

5. **High - support-library coverage is visible, but still not first-class accepted work.**
   - Paths: `dependency-backlog.json:21-687`, `porting.html:72-129`,
     `progress.md:17-36`.
   - Goal requirement at risk: `goal.md:35-40` require real denominators,
     fixture parity, edge-case coverage, and blockers for hard features. The
     latest support-library directives require bounded native components,
     activation gates, dependency-specific upstream/spec denominators, mapped
     fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant,
     and bounded install-attempt notes when missing packages matter.
   - Evidence: Pandoc's DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression needs are visible as gated rows.
     But all 37 support rows remain inactive (`candidate`, `deferred`, or one
     `blocked` QR row). There are still no accepted support manifests,
     dependency-specific PHP pass/fail ledgers, malformed/corrupt evidence
     records, activation records, or bounded install-attempt notes.

6. **High - Pandoc's manifest/status overstates readiness for the original document-conversion goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:424`,
     `lanes/pandoc/lane-status.json:4-13`,
     `dependency-backlog.json:93-269`, `dependency-backlog.json:336-426`,
     `dependency-backlog.json:645-646`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the manifest reports `mapped: 2276` of `total: 2276` and the
     status reports `estimatedProgress: 99`, but current PHP coverage is only
     `400` focused behavior checks and the full Haskell runner remains
     unexecuted. The status itself leaves live fetching/openURL, broader HTML
     parser parity, TeX/PlainMath/MathML, malformed HTML, PDF/package parsing,
     citation/CSL, ZIP/XML/HTML, Unicode/charset, and syntax highlighting
     behind future gates. It also carries an audit timestamp
     `2026-05-24 20:13 UTC`, which is in the future relative to this audit's
     `20:06 UTC` shell clock.

7. **High - markerPDF's denominator is still non-normalized even after the accepted reduced slice.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-16`,
     `lanes/markerpdf/lane-status.json:4-13`,
     `dependency-backlog.json:285-337`.
   - Goal requirement at risk: `goal.md:30` and `goal.md:35-40` say
     wrappers, shell-outs, whole applications, runtime launchers, and
     plan-only behavior must not count as native implementation progress.
   - Evidence: current markerPDF metadata reports `mapped: 164` against
     `total: 78`, mixing source-path inventory units with focused semantic
     mappings. The accepted native text-positioning slice is bounded, but the
     current working-tree status and blocker language still list Streamlit,
     FastAPI/Uvicorn, pypdfium/PDF rendering, Surya/OCR/Texify/Torch/Nougat,
     batch/chunk scripts, and model/runtime execution as broader gaps. Those
     must remain blockers or supplied-result boundaries, not progress.

8. **High - near-complete lane percentages are mostly pending or uncommitted dirty handoffs.**
   - Paths: `lanes/difftastic/lane-status.json:4-13`,
     `lanes/dolt/lane-status.json:4-13`,
     `lanes/esbuild/lane-status.json:4-13`,
     `lanes/gitoxide/lane-status.json:4-13`,
     `lanes/libsqlite/lane-status.json:4-13`,
     `lanes/lightningcss/lane-status.json:4-13`,
     `lanes/pandoc/lane-status.json:4-13`,
     `lanes/quadrable/lane-status.json:4-13`,
     `lanes/rclone/lane-status.json:4-13`,
     `lanes/readability/lane-status.json:4-13`,
     `lanes/syncthing/lane-status.json:4-13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require focused reviewable slices with passing tests,
     commits, cleanup, and new assignment only after verification.
   - Evidence: most lanes advertise `95-99%` progress or green focused tests,
     but their `latestCommit` fields are `pending`, `uncommitted`,
     "not committed", or broad prose batches. Recent Readability and esbuild
     handoffs were rejected because dirty scope did not match the reviewed
     evidence. These rows should be treated as unaccepted handoffs, not
     completed lane progress.

9. **Medium - status ledgers contain impossible or stale timestamps.**
   - Paths: `lanes/lightningcss/lane-status.json:10-13`,
     `lanes/pandoc/lane-status.json:10-13`, `progress.md:51`,
     `porting.html:34-38`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and `goal.md:45`
     require durable coordination with reliable phase, audit, blocker, and
     commit fields.
   - Evidence: Pandoc says `updated 2026-05-24 20:13 UTC`, ahead of this
     audit's `20:04 UTC` shell samples. LightningCSS moved again during the
     audit and now reports `HEAD 4756c15a0453` in `latestCommit`, while
     current `HEAD` is `958ad536ba58`. These records are not reliable
     coordination truth until regenerated from a frozen accepted snapshot.

## Required Next Intervention

Freeze writers/runners/status publishers long enough for two stable polls of
`HEAD`, tracked/default status rows, shortstat, manifest/status mtimes, and the
exact root process gate. Do not broaden lane work while pending handoffs remain
accumulated. If PID `1437381` or any other exact no-argument root reappears,
wait it out and do not start a duplicate. Next, accept or reject one owner-free
reduced lane batch whose dirty files exactly match its evidence, normalize that lane's
manifest/status count units, regenerate dashboard artifacts from the accepted
commit, then run one serialized no-argument `php tools/run-tests.php` only if
`pgrep -af '^php tools/run-tests\.php$'` remains empty on the frozen snapshot.
Keep support-library rows inactive until a base lane is accepted-ready or
accepted-blocked on the exact bounded component with its own denominator,
mapped fixtures, malformed/corrupt cases, PHP pass/fail ledger, and bounded
install-attempt notes where missing packages matter.
