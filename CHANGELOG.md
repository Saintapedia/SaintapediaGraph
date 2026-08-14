# Changelog

## Unreleased

- Detailed editor help page (`docs/Help-Saintapedia_Graph.wikitext`) and matching GitHub README (full parameter, template, limit, and install reference).

## 0.2.3 — 2026-08-14

Matches `main` after the 0.2.2 tag. Deploy this tag, not `v0.2.2`.

- CI badge points at `.github/workflows/ci.yml`.
- `$wgSaintapediaGraphMaxLimit` described as a per-query cap; dual mode can fetch up to 2×.
- mediawiki.org download links use the versioned tarball, not a `master` branch archive.
- `limit=` below 1 (including negative) is treated as a clamp and warned, same as over the max.
- Editor clamp warning is `limit $1 reduced to $2` (no “per Cargo query”).
- Maintenance script `SaintapediaGraph:importPages` imports the bundled help page and templates.

## 0.2.2 — 2026-08-13

Production-hygiene release. Deploy this tag (or `main` after it lands), not `v0.2.1`.

### Security (landed after the v0.2.1 tag)

- Allowlist Mermaid themes (`default`, `base`, `dark`, `forest`, `neutral`); unknown values fall back to `default` with a soft warning.
- Harden Mermaid `click` tooltips and URLs (control-character strip, quote/backslash encoding).
- Reject non-local click URLs. The builder only emits same-origin wiki paths.

### Editor feedback

- Warn when `limit=` exceeds `$wgSaintapediaGraphMaxLimit` (was a silent clamp). Dual mode states that the cap applies to each of the two Cargo queries.
- Empty Mermaid source no longer emits a blank `data-mermaid` mount.

### Release / docs

- GitHub Actions PHPUnit on PHP 8.1, 8.2, and 8.3 (workflow lands via PR #3).
- mediawiki.org extension page draft: `docs/mediawiki.org-Extension-SaintapediaGraph.wikitext`.
- Help page documents dual-table mode, `style_by`, `subgraph_by`, and cycle handling (no longer described as MVP-only).
- UI messages are English-only; documented as a known limit.
- CSP and deploy notes in the README, including a smoke checklist for `dev.saintapedia.org`.

## 0.2.1 — 2026-08-04

Initial public tree: hierarchy / edge / dual-table modes, `style_by`, `subgraph_by`, cycle warn/break, bundled Mermaid 10.9.1, templates, and unit tests.

The git tag `v0.2.1` points at this commit and **does not** include the theme/click hardening above.
