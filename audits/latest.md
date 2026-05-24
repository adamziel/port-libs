# Independent Audit - 2026-05-24T12:46Z

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `0bcb82e7 Refresh independent audit status`. I did not edit
lane implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T12:45Z and 2026-05-24T12:46Z
sampled HEAD before this audit commit: 0bcb82e7f9fb
recent history: 0bcb82e7 Refresh independent audit status; d9bfdc1d Record integration hold status; 46a07e64 Record integration hold status; a7c70e3d Refresh independent audit status; 48a0ac98 Record integration hold status
tracked dirty rows: 328 -> 327
default status rows including untracked: 17852 -> 17851
git diff --shortstat: 328 files changed, 236300 insertions(+), 30684 deletions(-) -> 327 files changed, 236402 insertions(+), 30686 deletions(-)
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-24 12:29:46 UTC from source 89260857cc71; sampled pre-audit HEAD is 0bcb82e7f9fb
dependency backlog: dependency-backlog.json updated 2026-05-24 12:29:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 12:45Z: no rows
pgrep -af '^php tools/run-tests\.php( |$)' at 12:46Z: no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear at
both samples, but the checkout was not stable enough: tracked rows,
untracked-inclusive rows, and shortstat moved between adjacent samples, and
every current lane handoff is still pending or uncommitted.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current dashboard drift sample:

```text
lane          current manifest/status        dashboard
difftastic    manifest 1082/863, status 3269 php pass 1077/851, 3245 php pass
esbuild       manifest/status 433 php pass   429 php pass
gitoxide      manifest 7177, status 7187 php pass 7152 php pass
libsqlite     manifest/status 350 php pass   348 php pass, 349 mapped
LightningCSS  manifest 2769 mapped, status 4075 php pass 2765 mapped, 4065 php
markerPDF     manifest/status 398/349/486    396/347/484
pandoc        manifest 1908 mapped, status 363 php pass 1891 mapped, 362 php
rclone        manifest/status 909 mapped/php 906 mapped/php
readability   status 3555 php pass           3545 php pass
syncthing     status 7930 php pass           7902 php pass
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `lanes/*/lane-status.json`, `goal.md:29`,
     `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, integrated,
     cleaned up, and assigned onward.
   - Evidence: the sampled pre-audit `HEAD` was `0bcb82e7f9fb`, but the tree
     still had hundreds of tracked dirty files and the sampled shape moved from
     `328` to `327` tracked dirty rows while shortstat changed to `327 files
     changed, 236402 insertions(+), 30686 deletions(-)`. All 12 lane-status
     files still record `latestCommit` as pending, uncommitted, not committed,
     or equivalent shared-dirty handoff prose.

2. **Critical - there is still no audit-acceptable root PHP result for the
   current snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/*/lane-status.json`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     periodically and failures recorded honestly.
   - Evidence: the required exact process gate was clear at 12:45Z and
     12:46Z, but the checkout changed between samples and no coherent lane
     batch has been accepted. Focused lane-green results remain lane-local
     evidence, not root evidence for the sampled pre-audit `0bcb82e7f9fb`
     plus the current dirty tree.

3. **High - the dashboard and compact summary are stale and internally
   misleading against current source metadata.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:56` through
     `porting.html:67`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: coordination files and dashboard must show the
     current denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html` still claims source snapshot `89260857cc71` while
     the sampled pre-audit `HEAD` was `0bcb82e7f9fb`. Live metadata has moved past the
     dashboard for Difftastic, esbuild, Gitoxide, libsqlite, LightningCSS,
     markerPDF, Pandoc, rclone, Readability, and Syncthing. Some live metadata
     is also out of sync internally, for example Difftastic manifest
     `1082/863` versus status `1077/851`, and Gitoxide manifest `7177` PHP
     pass versus status `7187`.

4. **High - support-library coverage is still planning-only under the latest
   rich-function directive.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:61`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:179`,
     `dependency-backlog.json:195`, `dependency-backlog.json:233`,
     `porting.html:71` through `porting.html:129`.
   - Goal requirement at risk: the 2026-05-24 11:59 UTC support-library
     directive requires bounded native PHP components, activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can honestly run.
   - Evidence: the backlog accounts on paper for Pandoc DOC, DOCX/OpenXML, PDF
     handoff/text extraction, EPUB, ODT/OpenDocument, templates, citations,
     math, tables, package containers, XML/HTML, Unicode/charset, JSON/YAML,
     and archive/compression, plus shared non-Pandoc dependencies. There are
     still `0` active support rows and no support-library-specific manifests or
     pass/fail evidence. These rows are routing, not accepted support-port
     progress.

5. **High - markerPDF still mixes native PDF progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json:5`, `lanes/markerpdf/lane-status.json:12`,
     `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the main native deliverable.
   - Evidence: markerPDF has real native PDF text/filter/outline/resource
     progress. It also keeps Poetry/package metadata, model-runtime dependency
     graphs, Pandoc/XeLaTeX helper workflows, Streamlit/FastAPI runtime plans,
     top-level multiprocessing and chunk-convert shell boundaries,
     OCRMyPDF/Tesseract/Ghostscript readiness, and Texify/Torch/Nougat model
     handoffs in the same mapped surface. Those should remain preflight,
     supplied-boundary, or blocker records, not native port progress.

6. **Medium - status timestamps are not reliable enough for acceptance.**
   - Paths: `lanes/esbuild/lane-status.json:10`, `progress.md`.
   - Goal requirement at risk: `progress.md` and lane status must provide
     current audit status and current work that supervisors can trust.
   - Evidence: the system clock during this audit was
     `2026-05-24T12:45:48Z`, but esbuild lane status claims
     `verified 2026-05-24 13:05 UTC`. Earlier progress entries also record
     future lane audit timestamps as prior audit findings. Future-dated
     handoffs should not be accepted until the status publisher clock/source is
     corrected or the handoff is revalidated from a frozen snapshot.

7. **Medium - `progress.md` active-lane handoffs remain stale outside the
   snapshot notes.**
   - Paths: `progress.md:148` through `progress.md:161`,
     `lanes/*/lane-status.json:11`, `goal.md:44`.
   - Goal requirement at risk: `progress.md` must include current owner/session,
     next task per lane, blockers, and percentage estimates.
   - Evidence: the Active Lanes table still names older handoffs such as
     markerPDF benchmark file-inventory planning, libsqlite WAL FULL-sync,
     Pandoc NativeWriter figure/citation, rclone VFS Statfs/usage, and esbuild
     automatic JSX key/spread fallback. Current lane statuses describe later
     markerPDF PDF text-leading, libsqlite substr/substr JSON RHS, Pandoc LaTeX
     OrderedList, rclone Account.String/speed, esbuild DataURL, and other newer
     handoffs.

8. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json:12`, `goal.md:35` through `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes at
     `98%` or `99%`, while aggregate root verification is pending, support
     libraries have no active bounded ports, current lane work is uncommitted,
     and full upstream runners remain bounded or unexecuted for multiple lanes
     including Difftastic, Gitoxide, markerPDF, Pandoc, rclone provider/mount
     suites, Syncthing, and esbuild release-extra `make test-all` targets.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Require two stable polls of `HEAD`, tracked rows, untracked-inclusive rows,
shortstat, exact process gates, dependency/dashboard counts, status timestamps,
and relevant log mtimes. Then accept exactly one coherent lane batch with
manifest/status schema and count normalization, run focused lane verification
plus `git diff --check`, activate only support-library rows whose base-lane
gate is accepted or truly blocked, add dependency-specific support manifests
before counting support progress, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, and run one serialized
no-argument `php tools/run-tests.php` only if the exact process gate remains
empty on that frozen snapshot.
