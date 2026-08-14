# Saintapedia Graph

[![PHPUnit](https://github.com/Saintapedia/SaintapediaGraph/actions/workflows/phpunit.yml/badge.svg)](https://github.com/Saintapedia/SaintapediaGraph/actions/workflows/phpunit.yml)

MediaWiki extension that turns [Extension:Cargo](https://www.mediawiki.org/wiki/Extension:Cargo) data into interactive Mermaid flowcharts — org charts, funder→fundee networks, styled/grouped graphs, and dual-table diagrams.

| | |
|---|---|
| **Version** | 0.2.2 |
| **Directory** | `extensions/SaintapediaGraph` |
| **Parser functions** | `{{#saintapedia_graph:…}}`, alias `{{#cargo_mermaid:…}}` |
| **Requires** | MediaWiki 1.39+, PHP 8.1+, **Cargo** |
| **Mermaid** | **Bundled** (`resources/lib/mermaid.min.js` 10.9.1) — Extension:Mermaid is **not** required |
| **Languages** | **English only** — UI / error / warning messages are `en` + `qqq`. Other languages fall back to English. |
| **Help** | `docs/Help-Saintapedia_Graph.wikitext` → `Help:Saintapedia Graph` |
| **Templates** | `templates/Org_chart.wikitext`, `templates/Funding_network.wikitext` |
| **mediawiki.org** | Draft: `docs/mediawiki.org-Extension-SaintapediaGraph.wikitext` → [Extension:SaintapediaGraph](https://www.mediawiki.org/wiki/Extension:SaintapediaGraph) |
| **Changelog** | [CHANGELOG.md](CHANGELOG.md) |

Deploy **0.2.2** (or `main` after this release). The older `v0.2.1` tag is the initial commit and is missing theme/click hardening.

## Install

1. Place this folder at `extensions/SaintapediaGraph` (name must match the ResourceLoader path).
2. After Cargo in `LocalSettings.php` / Canasta `settings.yaml`:

```php
wfLoadExtension( 'Cargo' );
wfLoadExtension( 'SaintapediaGraph' );
```

3. Check `Special:Version` for **Saintapedia Graph 0.2.2**.
4. Optional: import help + templates from `docs/` and `templates/`.

No `update.php` run is required (no database tables).

## Configuration

| Variable | Default | Meaning |
|----------|---------|---------|
| `$wgSaintapediaGraphDefaultDirection` | `TD` | `TD` / `LR` / `BT` / `RL` |
| `$wgSaintapediaGraphDefaultLimit` | `500` | Default `limit=` |
| `$wgSaintapediaGraphMaxLimit` | `1000` | Hard cap on rows per query |
| `$wgSaintapediaGraphClickable` | `true` | Nodes link to wiki pages |
| `$wgSaintapediaGraphDefaultTheme` | `default` | Mermaid theme: `default`, `base`, `dark`, `forest`, `neutral` (invalid values fall back to `default`; also embedded in diagram source via `%%{init}%%`) |
| `$wgSaintapediaGraphUseStandaloneRenderer` | `true` | Interactive render (false = raw source only) |
| `$wgSaintapediaGraphWarnCycles` | `true` | Warn when directed cycles exist |
| `$wgSaintapediaGraphBreakCycles` | `false` | Drop cycle-closing edges |
| `$wgSaintapediaGraphStylePalette` | 10 colors | Fills for `style_by` |

Theme is applied in the generated Mermaid source (`%%{init: {theme:…}}%%`). Only built-in Mermaid themes are accepted (`default`, `base`, `dark`, `forest`, `neutral`); anything else falls back to `default` and surfaces a soft warning. The HTML `data-theme` attribute mirrors the validated theme for debugging; rendering uses the source init block (client JS keeps a global `theme: 'default'` only as a fallback).

Node clicks require Mermaid `securityLevel: 'loose'`. The builder only emits **same-origin local paths** (`/wiki/…`, `index.php?…`) from `Title::getLocalURL()`, never absolute or protocol-relative URLs.

## Content Security Policy

The renderer loads **only** the bundled, same-origin ResourceLoader module `ext.saintapediaGraph` (PHP + `resources/lib/mermaid.min.js`). It does not fetch Mermaid from a CDN.

If the wiki sets a CSP:

- `script-src` must allow the wiki origin (ResourceLoader).
- `style-src` should allow `'unsafe-inline'` **or** the wiki origin — Mermaid 10 writes some SVG presentation attributes/styles while drawing.
- Do not add `cdn.jsdelivr.net` / `unpkg.com` for this extension.

If a strict CSP blocks inline SVG styles, diagrams may render unstyled. That is a wiki CSP choice, not a missing remote script.

## Languages

User-visible strings (errors, cycle/theme warnings, `Special:Version` description, client render-failure title) ship in **English only** (`i18n/en.json`). Message documentation is in `i18n/qqq.json`.

MediaWiki will show English if `$wgLanguageCode` is not `en`. That is an accepted limit for 0.2.2. Additional languages can be added later as `i18n/<code>.json` files; there is no translatewiki.net project yet.

## Modes

| Mode | Parameters |
|------|------------|
| **Hierarchy** | `tables=` + `parent_field=` |
| **Edge** | `tables=` + `source_field=` + `target_field=` |
| **Dual** | `nodes_table=` + `edges_table=` + source/target fields |

Do not mix hierarchy and edge parameters. Dual mode runs **two** Cargo queries (counts as two expensive parser functions).

### Dual-mode identity tip

Edge endpoints (`Funder`, `Recipient`) are usually **page titles**. Prefer:

```wikitext
|node_id=_pageName
|node_label=Name
```

so edges match organization pages. If you use `node_id=Name`, **Name must equal the page title**.

`style_by=` / `subgraph_by=` values must be real Cargo column names.

## Examples

### Org chart with colors and groups

```wikitext
{{#saintapedia_graph:
tables=Organizations
|fields=_pageName=Name,ParentOrg,Type,Country
|parent_field=ParentOrg
|node_id=Name
|style_by=Type
|subgraph_by=Country
|direction=TD
}}
```

Or: `{{Org chart|style=Type|subgraph=Country}}`

### Dual-table funding network

```wikitext
{{#saintapedia_graph:
nodes_table=Organizations
|nodes_fields=_pageName=Name,Type,Country
|edges_table=Grants
|edges_fields=Funder,Recipient,Amount
|edges_where=Year>=2023
|node_id=_pageName
|node_label=Name
|source_field=Funder
|target_field=Recipient
|edge_label=Amount
|style_by=Type
|subgraph_by=Country
|direction=LR
|theme=forest
}}
```

Or: `{{Funding network|style=Type|subgraph=Country|edges_where=Year>=2023}}`

### Multi-table join (single query)

```wikitext
{{#saintapedia_graph:
tables=Grants,Organizations
|join on=Grants.Funder=Organizations._pageName
|fields=Grants.Funder,Grants.Recipient,Grants.Amount,Organizations.Type
|source_field=Funder
|target_field=Recipient
|edge_label=Amount
}}
```

## Debug

- `format=raw` — print Mermaid source
- Hard-refresh after extension updates (Ctrl+Shift+R)
- Cycles: `warn_cycles=yes` (default), `break_cycles=yes` to drop back-edges

## Tests

Standalone PHPUnit (no MediaWiki core / Cargo):

```sh
composer install
vendor/bin/phpunit
```

Or: `docker run --rm -v "$PWD":/app -w /app php:8.2-cli php vendor/bin/phpunit`

CI runs that suite on PHP 8.1, 8.2, and 8.3.

## Smoke on dev.saintapedia.org

After `wfLoadExtension( 'SaintapediaGraph' )` on the Canasta/dev wiki:

1. `Special:Version` lists **Saintapedia Graph 0.2.2** and the `ext.saintapediaGraph` module.
2. Import `docs/Help-Saintapedia_Graph.wikitext` → `Help:Saintapedia Graph`.
3. Import `templates/Org_chart.wikitext` → `Template:Org chart` and `templates/Funding_network.wikitext` → `Template:Funding network`.
4. Render an org chart (`parent_field` or `{{Org chart}}`) with real Cargo rows.
5. Render a dual-table funding graph (`{{Funding network}}` or `nodes_table` + `edges_table`).
6. Click a node — it must stay on-wiki (same origin).
7. `theme=forset` (typo) should still render and show the unknown-theme warning.
8. Hard-refresh (Ctrl+Shift+R) so the new ResourceLoader module is not a cached 0.2.1 payload.

Local smoke (already done) does not replace this wiki pass.

## License

- Extension: GPL-2.0-or-later (`COPYING`)
- Bundled Mermaid: MIT — see `THIRD-PARTY.md`
