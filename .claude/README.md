# .claude

Committed documentation for Claude Code (and human contributors) working in this repo.

- [`start-session.md`](start-session.md) — **read first when starting a new session.**
  Current versions, what's built, what's next, conventions, and gotchas in brief.
- [`design.md`](design.md) — the WP Arzo design system: color tokens, components,
  contrast rules, and conventions for new UI.
- [`ROADMAP.md`](ROADMAP.md) — product & engineering roadmap (feature registry, freemium
  via Freemius, backup/versioning, AI/MCP, phasing).
- [`skills/`](skills/) — task-specific playbooks:
  - [`wp-arzo-architecture`](skills/wp-arzo-architecture/SKILL.md) — boot, routing, and
    the front-end ↔ back-end JSON contract.
  - [`wp-arzo-feature-module`](skills/wp-arzo-feature-module/SKILL.md) — add a dashboard
    feature via the registry (the modern way).
  - [`wp-arzo-add-feature`](skills/wp-arzo-add-feature/SKILL.md) — add a legacy console tab
    or AJAX operation without breaking routing.
  - [`wp-arzo-security`](skills/wp-arzo-security/SKILL.md) — capability/nonce/sanitize/
    escape checklist and the dangerous sinks in this plugin.
  - [`wp-arzo-release`](skills/wp-arzo-release/SKILL.md) — version-bump and verification
    checklist.

See also the root [`CLAUDE.md`](../CLAUDE.md) for the high-level architecture and
security baseline.

> Local-only Claude state (`.claude/settings.local.json`, `.claude/.cache/`) is
> git-ignored; the docs and skills above are committed intentionally.
