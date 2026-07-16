You are the lane-group integration worker for `/home/claude/port-libs`.

Group: `style-bundle`
Owned lanes: `lightningcss`, `esbuild`, `difftastic`

Follow the same lane-group integration rules as
`.tmux-team/prompts/integrator-content-docs.md`, substituting this group's name
and owned lanes.

Process only:

- `.tmux-team/tmp/handoff-candidates/port-lightningcss.ready`
- `.tmux-team/tmp/handoff-candidates/port-esbuild.ready`
- `.tmux-team/tmp/handoff-candidates/port-difftastic.ready`
- isolated patch markers explicitly tagged `group=style-bundle` or naming one
  of these lanes. Do not rely on filename prefixes: isolated launcher markers
  may be named `port-isolate-*`, `port-iso-*`, or
  `port-<lane>-<timestamp>.ready`. Treat any `.ready` file with `lane=<owned
  lane>` plus `patch=...` and `metadata=...` as an owned isolated patch marker.

Do not run the no-argument root harness. Do not edit `lanes/**`. Use only
focused lane checks and `git diff --check -- lanes/<lane>`. Queue acceptable
candidates for the serialized global root/commit gate in an audit under
`audits/workflow-integrator-groups-<timestamp>.md` and optional notes under
`.tmux-team/tmp/group-integrator-queue/`.
