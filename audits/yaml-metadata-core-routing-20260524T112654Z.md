# YAML Metadata Core Routing - 2026-05-24T11:39Z

Scope read: `dependency-backlog.json`,
`audits/rich-support-dependency-priority-audit-20260524T111227Z.md`,
`audits/support-library-essential-dependency-routing-20260524T105940Z.md`,
`lanes/pandoc/lane-status.json`, and
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

Coordination gate: waited until
`port-support-tracker-integrator-20260524T110423Z` had no Codex/node child.
The corrected dependency follow-up then appeared in `HEAD` as
`Record support dependency follow-up`; the named corrected-integration hold
artifact was not present. Two pre-edit file-state polls for
`dependency-backlog.json` and
`audits/rich-support-dependency-priority-audit-20260524T111227Z.md` were taken
more than 20 seconds apart and matched by inode, size, mtime, and content hash.

No lane files, manifests, statuses, dashboards, publisher artifacts, prompts,
logs, review artifacts, support follow-up integration artifacts, root harnesses,
staging, commits, pushes, process environments, credential stores, provider
configs, live-service tests, cloud remotes, OAuth/browser auth state, or
secret-bearing inputs were inspected or changed.

## Decision

`yaml-metadata-core` was missing from the 36-row backlog, so one inactive
`candidate` / `high` row was added. The row is needed only by `pandoc` and was
placed next to `json-json5-document-core` because the audit identifies Pandoc
YAML metadata as the missing counterpart to existing JSON metadata coverage.

The row is bounded to Pandoc YAML metadata/front matter conversion needs:
YAML metadata blocks, front matter, scalar/list/map metadata values, stable
diagnostics, fixture-backed round-trip metadata rendering, and handoff to
Pandoc native document metadata.

The row explicitly excludes full YAML application frameworks, arbitrary object
deserialization, live includes, network fetches, shelling out to Pandoc,
Ruby/Python/Node YAML tools, whole static-site generators, credentials,
secret-bearing configs, and secret-bearing inputs.

It requires a YAML-specific spec/upstream denominator, mapped Pandoc metadata
fixtures, PHP pass/fail counts, malformed indentation/anchor/alias/tag/
duplicate-key/invalid-scalar/front-matter-boundary cases, stable diagnostics,
fixture-backed round-trip rendering, and honest full-suite Pandoc runner
evidence or bounded upstream exact-selector/static evidence with blockers
recorded.

The row was not activated.

## Before / After

- Before edit: 36 dependency rows; `yaml-metadata-core` absent.
- After edit: 37 dependency rows; `yaml-metadata-core` present exactly once.
- Rows added: `yaml-metadata-core`.
- Rows activated: none.

## Remaining Gates

Review, integration, and dashboard gates remain unresolved for activation. This
tracker follow-up only records the inactive candidate row; it does not make any
support dependency active or regenerate dashboard artifacts.

## Validation

- `jq empty dependency-backlog.json` passed with no output.
- Duplicate dependency id check passed:
  `([.items[].id] | length) == ([.items[].id] | unique | length)`.
- Required key check passed for every dependency item.
- Count/status/priority summary after edit: 37 rows; statuses are
  `blocked: 1`, `candidate: 25`, `deferred: 11`; priorities are
  `critical: 4`, `high: 27`, `medium: 6`; active rows: 0.
- Targeted `jq` check for `yaml-metadata-core` passed: exactly one row,
  `status: candidate`, `priority: high`, `neededBy: ["pandoc"]`, with front
  matter, Pandoc shell-out exclusion, secret-bearing input exclusion,
  YAML-specific evidence, and front-matter-boundary requirements present.
- `git diff --check -- dependency-backlog.json audits/yaml-metadata-core-routing-20260524T112654Z.md`
  passed with no output.
- No-index whitespace check for this untracked routing artifact emitted no
  whitespace warnings:
  `git diff --no-index --check -- /dev/null audits/yaml-metadata-core-routing-20260524T112654Z.md`.
