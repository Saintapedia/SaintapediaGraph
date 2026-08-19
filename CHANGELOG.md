# Changelog

## Unreleased

- `importPages --examples` imports collision-safe '''OrgDemo''' / '''GrantDemo''' Cargo templates, a fictional archdiocese → diocese → parish tree, sample grants, and `Saintapedia Graph demo`. Not imported by default (a wiki may already have Organization / Grant).
- mediawiki.org draft documents the example import. Paste `docs/mediawiki.org-Extension-SaintapediaGraph.wikitext` by hand; it is not a wiki catalog page.
- Help / README / mediawiki.org draft: gallery of uses (dioceses, deaneries, orders, schools, consecrators, grants, patrons, clusters).
- Example series lives under **Help:Saintapedia Graph/…** — one page per use with the snippet and the live graph together. `Saintapedia Graph demo` redirects to `Help:Saintapedia Graph/Examples`.
- Special:Import dumps on GitHub: `docs/import/SaintapediaGraph-help.xml` and `SaintapediaGraph-examples.xml` (`php maintenance/buildImportXml.php`).
- Each example has a Cargo template (`#cargo_declare` + `#cargo_store`). Graph wrappers are not Cargo templates. After Special:Import: null-edit the template, then '''Create data table''' (`Help:Saintapedia Graph/Create tables`).

## 0.2.5 — 2026-08-15

Deploy this tag (or `main` after it), not `v0.2.4`. The `v0.2.4` tag is docs-only and still initializes Mermaid with `securityLevel: 'loose'`.

### Security

- Mermaid `securityLevel: 'loose'` → `'antiscript'` in both `%%{init}%%` and `mermaid.initialize()`. Click lines still work; scripts inside SVG are blocked.
- Validate `$wgSaintapediaGraphStylePalette` values before interpolating them into `classDef` (hex, named color, or a tight `rgb()`/`hsl()` form). Invalid slots are skipped.

### Correctness

- Empty Mermaid source returns an error box before `addModules()`, the instance counter, and the standalone-off path.
- Catch `\Throwable` (not only `Exception`) around Cargo work; chain the previous exception on query failure.
- Coerce `offset` to a non-negative integer before passing it to Cargo.
- Escape backslashes in node labels (`\` → `\\`) instead of dropping them.
- Drop the deprecated `escape()` decode fallback and the post-render `el.textContent` source fallback.
- MediaWiki refuses to load the extension on PHP &lt; 8.1 (`requires.platform.php`).
- Cycle / broken-edge warnings use `{{PLURAL}}`.

### Cleanup

- Remove unused `MermaidEscaper::clickTarget()`.
- `ExtensionMessagesFiles` stays registered (`{{#saintapedia_graph:}}` / `{{#cargo_mermaid:}}`).

## 0.2.4 — 2026-08-14

Docs-only. Same code as 0.2.3 plus the detailed help/README so the tag matches `main`. **Do not deploy this tag if you want the 0.2.5 security fixes.**

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
