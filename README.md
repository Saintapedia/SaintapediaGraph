# Saintapedia Graph

[![PHPUnit](https://github.com/Saintapedia/SaintapediaGraph/actions/workflows/ci.yml/badge.svg)](https://github.com/Saintapedia/SaintapediaGraph/actions/workflows/ci.yml)

MediaWiki extension that turns [Extension:Cargo](https://www.mediawiki.org/wiki/Extension:Cargo) tables into interactive [Mermaid](https://mermaid.js.org/) flowcharts — org charts, funder→fundee networks, styled/grouped graphs, and dual-table diagrams.

The **editor-facing** reference is the bundled help page (`docs/Help-Saintapedia_Graph.wikitext` → `Help:Saintapedia Graph` after import). This README is the GitHub / admin copy of the same contract.

| | |
|---|---|
| **Version** | 0.2.5 |
| **Directory** | `extensions/SaintapediaGraph` (name must match the ResourceLoader path) |
| **Parser functions** | `{{#saintapedia_graph:…}}`, alias `{{#cargo_mermaid:…}}` |
| **Requires** | MediaWiki 1.39+, PHP 8.1+, **Cargo** |
| **Mermaid** | Bundled 10.9.1 (`resources/lib/mermaid.min.js`) — Extension:Mermaid is **not** required |
| **Languages** | **English only** for errors / warnings (`i18n/en.json` + `qqq.json`) |
| **Help** | [docs/Help-Saintapedia_Graph.wikitext](docs/Help-Saintapedia_Graph.wikitext) |
| **Templates** | [templates/Org_chart.wikitext](templates/Org_chart.wikitext), [templates/Funding_network.wikitext](templates/Funding_network.wikitext) |
| **mediawiki.org** | Draft: [docs/mediawiki.org-Extension-SaintapediaGraph.wikitext](docs/mediawiki.org-Extension-SaintapediaGraph.wikitext) → [Extension:SaintapediaGraph](https://www.mediawiki.org/wiki/Extension:SaintapediaGraph) |
| **Changelog** | [CHANGELOG.md](CHANGELOG.md) |

Deploy **`v0.2.5` or `main`**. Do not deploy `v0.2.4` / `v0.2.3` / `v0.2.2` / `v0.2.1` — `v0.2.4` is docs-only and still uses Mermaid `securityLevel: 'loose'`.

## Contents

- [Install](#install)
- [Configuration](#configuration)
- [Modes](#modes)
- [Parameters](#parameters)
- [Templates](#templates)
- [Limits, clicks, cycles, themes](#limits-clicks-cycles-themes)
- [Examples](#examples)
- [Maintenance](#maintenance)
- [CSP and languages](#csp-and-languages)
- [Tests and smoke](#tests-and-smoke)
- [License](#license)

## Install

1. Place this folder at `extensions/SaintapediaGraph`.
2. After Cargo in `LocalSettings.php` / Canasta `settings.yaml`:

```php
wfLoadExtension( 'Cargo' );
wfLoadExtension( 'SaintapediaGraph' );
```

3. Check `Special:Version` for **Saintapedia Graph 0.2.5**.
4. Import help + templates — **Special:Import** (no shell) or the maintenance script.

**Special:Import:** download a dump, then `Special:Import` → upload (needs the `import` right):

| Dump | Contents |
|---|---|
| [SaintapediaGraph-help.xml](https://raw.githubusercontent.com/Saintapedia/SaintapediaGraph/main/docs/import/SaintapediaGraph-help.xml) | Help, `Org chart`, `Funding network`, right-hand examples table |
| [SaintapediaGraph-examples.xml](https://raw.githubusercontent.com/Saintapedia/SaintapediaGraph/main/docs/import/SaintapediaGraph-examples.xml) | Help **plus** OrgDemo / GrantDemo, Demo … pages, and `Help:Saintapedia Graph/…` |

Also in the repo at `docs/import/`. Regenerated with `php maintenance/buildImportXml.php`.

```sh
# MediaWiki 1.40+
php maintenance/run.php SaintapediaGraph:importPages
# MediaWiki 1.39
php extensions/SaintapediaGraph/maintenance/importPages.php

# Preview:  --dry-run
# Replace:  --overwrite
# Also import OrgDemo/GrantDemo sample diocese/parish pages:
#   --examples
```

That writes `Help:Saintapedia Graph`, `Template:Org chart`, `Template:Funding network`, and `Template:Saintapedia Graph examples` (right-hand series table). With `--examples` it also writes `Template:OrgDemo`, `Template:GrantDemo`, the sample diocese/parish pages, and the **Help:Saintapedia Graph/…** example series (code + live graph on each page). Table names are **OrgDemo** / **GrantDemo** so they do not collide with a wiki's Organization or Grant templates. The old title `Saintapedia Graph demo` redirects to `Help:Saintapedia Graph/Examples`.

After `--examples`, if [[Special:CargoTables]] has no rows:

```sh
php extensions/Cargo/maintenance/cargoRecreateData.php --table OrgDemo
php extensions/Cargo/maintenance/cargoRecreateData.php --table GrantDemo
```

No `update.php` run is required (no database tables of our own).

## Configuration

| Variable | Default | Meaning |
|----------|---------|---------|
| `$wgSaintapediaGraphDefaultDirection` | `TD` | `TD` / `LR` / `BT` / `RL` (`TB` is treated as `TD`) |
| `$wgSaintapediaGraphDefaultLimit` | `500` | Default `limit=` **per Cargo query** |
| `$wgSaintapediaGraphMaxLimit` | `1000` | Hard cap **per Cargo query**. Dual mode runs two queries, so a page can fetch up to 2× this many rows. |
| `$wgSaintapediaGraphClickable` | `true` | Nodes link to wiki pages |
| `$wgSaintapediaGraphDefaultTheme` | `default` | `default`, `base`, `dark`, `forest`, `neutral` |
| `$wgSaintapediaGraphUseStandaloneRenderer` | `true` | Interactive SVG (`false` = Mermaid source only) |
| `$wgSaintapediaGraphWarnCycles` | `true` | Warn when directed cycles exist |
| `$wgSaintapediaGraphBreakCycles` | `false` | Drop cycle-closing edges |
| `$wgSaintapediaGraphStylePalette` | 10 hex colors | Fills for `style_by` |

Theme is written into the generated source (`%%{init: {theme:…}}%%`). Unknown themes fall back to `default` and emit `saintapediagraph-warning-unknown-theme`. The HTML `data-theme` attribute mirrors that validated theme; client JS keeps a global `theme: 'default'` only as a fallback.

## Modes

Pick **one** per diagram. Mixing `parent_field` with `source_field`/`target_field` is an error. Dual mode is selected when both `nodes_table` and `edges_table` are set.

| Mode | Required | Edges | Cargo queries |
|------|----------|-------|----------------|
| **Hierarchy** | `tables=` + `parent_field=` | parent → child | 1 |
| **Edge** | `tables=` + `source_field=` + `target_field=` | source → target | 1 |
| **Dual** | `nodes_table=` + `edges_table=` + source/target | source → target | **2** (two expensive parser functions) |

**Hierarchy:** each row is a child. Empty `parent_field` = root. Missing parents are still drawn. A synthetic parent can inherit a subgraph from its first child.

**Edge:** each row is one arrow. `style_by` / `subgraph_by` come from that same row and apply to the **source** node.

**Dual:** first query = nodes (label, click, color, group); second = edges. Endpoints missing from the node query are still drawn, unstyled. Edge rows never overwrite node styles.

Dual-mode identity: grant endpoints are usually **page titles**. Use `node_id=_pageName` and `node_label=Name`. If you use `node_id=Name`, Name must equal the page title.

`style_by=` / `subgraph_by=` must be real Cargo column names.

## Parameters

Names are case-insensitive; spaces and hyphens become underscores (`join on` = `join_on`).

### Cargo (hierarchy / edge)

| Parameter | Role |
|-----------|------|
| `tables` / `table` | Required. Comma-separated Cargo tables. |
| `fields` | Columns to select. Needed diagram fields are appended if missing. |
| `where` | Filter. |
| `join on` | Required when more than one table is listed. |
| `group by` / `having` | Aggregation (Cargo rules). |
| `order by` | Sort. |
| `limit` | Max rows **per query** (see [Limits](#limits-clicks-cycles-themes)). |
| `offset` | Skip rows (single-query modes). Dual edge query always uses offset 0. |

### Dual query

| Parameter | Role |
|-----------|------|
| `nodes_table` / `edges_table` | Required together. |
| `nodes_fields` / `edges_fields` | Per-query columns. Empty `edges_fields` defaults to source, target, optional label. |
| `nodes_where` / `edges_where` | Per-query filters. |
| `nodes_join_on` / `edges_join_on` | Per-query joins. |
| `nodes_order_by` / `edges_order_by` | Per-query sort. |

### Diagram

| Parameter | Default | Role |
|-----------|---------|------|
| `direction` | site `TD` | `TD`/`TB`, `LR`, `BT`, `RL` |
| `node_id` | `_pageName` | Stable node identity |
| `node_label` | `node_id` (dual may pick `Name`) | Visible text |
| `page_field` | `_pageName` (hierarchy/dual); endpoint itself (edge) | Click target title |
| `parent_field` | — | Hierarchy only |
| `source_field` / `target_field` | — | Edge and dual |
| `edge_label` | — | Arrow caption (~64 chars) |
| `style_by` | — | Color by column |
| `subgraph_by` | — | Group by column |
| `link_style` | `-->` | `-->`, `-.->`, `==>`, `---`, `<-->` |
| `clickable` | site `yes` | `yes`/`no` (`true`/`false`, `1`/`0`, `on`/`off`) |
| `theme` | site `default` | Mermaid built-ins only |
| `warn_cycles` | site `yes` | Warn on directed cycles |
| `break_cycles` | site `no` | Drop back-edges |
| `format` | `mermaid` | `mermaid` or `raw` / `source` / `text` |

## Templates

| Template | Mode | Useful arguments |
|----------|------|------------------|
| `{{Org chart}}` | Hierarchy (defaults assume `Organizations`; pass `table=` for Parishes, OrgDemo, …) | `table`, `parent`, `id`, `label`, `style`, `subgraph`, `where`, `order`, `direction`, `limit` (default 200), `theme`, `clickable`, `warn_cycles`, `break_cycles` |
| `{{Funding network}}` | Dual nodes + edges (defaults `Organizations` / `Grants`; pass `nodes=` / `edges=` for OrgDemo / GrantDemo) | `nodes`, `edges`, `source`, `target`, `id` (default `_pageName`), `label` (default `Name`), `edge_label` (default `Amount`), `style`, `subgraph`, `edges_where` / `where`, `nodes_where`, `direction` (default `LR`), `theme` (default `forest`), `limit` (default 300) |

`style` / `subgraph` must be real columns. See the help page for the full argument → parser-function map.

## Limits, clicks, cycles, themes

**Limits.** Omitted `limit=` uses `$wgSaintapediaGraphDefaultLimit`. The value is then clamped to `[1, $wgSaintapediaGraphMaxLimit]`. Over the max or below 1 (including negatives) still draws the graph and warns: `limit $1 reduced to $2; the graph may be missing rows.` Dual mode applies that cap to **each** query and adds a second line. Dual also counts as two expensive parser functions (`$wgExpensiveParserFunctionLimit`).

**Clicks.** `clickable=yes` emits Mermaid `click` lines. Only same-origin paths from `Title::getLocalURL()` (`/wiki/…`, `index.php?…`) are kept. Absolute and protocol-relative URLs are dropped. Mermaid is initialized with `securityLevel: 'antiscript'`; click lines still work under that level and scripts inside SVG are blocked. Interactive SVG needs JavaScript; noscript shows the source.

**Cycles.** After the graph is built, directed cycles can be warned (`warn_cycles`) and/or broken (`break_cycles`). Counts may overlap on dense graphs. Path labels still use internal ids.

**Themes.** Allowlist only. Invalid `theme=` → `default` + warning.

**One query vs dual.** `tables=A,B` + `join on=` is one result set. `nodes_table` + `edges_table` is two queries — use it when Type/Country live on the organization row.

Not in 0.2.5: N-hop expand, Sankey, hover field tooltips, SVG/PNG export.

## Examples

After `--examples`, each use is its own Help page (wikitext + live graph): [Help:Saintapedia Graph/Examples](docs/Help-Saintapedia_Graph.wikitext). Patterns below are the ones most wikis copy first.

| Use | Mode | Starter |
|---|---|---|
| Archdiocese → diocese → parish | Hierarchy | `{{Org chart\|table=Parishes\|parent=Diocese\|id=_pageName}}` |
| One diocese only | Hierarchy | add `where=Diocese="…"` |
| Religious houses in a province | Hierarchy | `parent_field=Province` |
| Catholic schools | Hierarchy | parent = parish; `style_by=Level` |
| Episcopal lineage | Hierarchy | `parent_field=Consecrator` |
| Grants this year | Dual | `{{Funding network\|nodes=Places\|edges=Grants\|edges_where=Year>=2023}}` |
| One foundation's giving | Edge | `where=Funder="…"` |
| Twin / clustered parishes | Edge | `link_style=<-->` |
| Patron saint → place | Edge | `source_field=Saint` `target_field=Place` |
| Suppressed see → successor | Edge | `source_field=_pageName` `target_field=SucceededBy` |

### Org chart

Works for archdioceses, dioceses, parishes, orders — any parent/child Cargo table. The bundled sample uses **OrgDemo**:

```wikitext
{{#saintapedia_graph:
tables=OrgDemo
|fields=_pageName=Name,ParentOrg,Type,Country
|parent_field=ParentOrg
|node_id=Name
|style_by=Type
|subgraph_by=Country
|direction=TD
}}
```

Or: `{{Org chart|table=OrgDemo|style=Type|subgraph=Country}}`

On a real diocese wiki: `{{Org chart|table=Parishes|parent=Diocese|id=_pageName}}`.

### Dual-table funding network

```wikitext
{{#saintapedia_graph:
nodes_table=OrgDemo
|nodes_fields=_pageName=Name,Type,Country
|edges_table=GrantDemo
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

Or: `{{Funding network|nodes=OrgDemo|edges=GrantDemo|style=Type|subgraph=Country|edges_where=Year>=2023}}`

### Patronage or twin parishes (edge)

```wikitext
{{#saintapedia_graph:
tables=Patronage
|fields=Saint,Place,Feast
|source_field=Saint
|target_field=Place
|edge_label=Feast
|direction=LR
}}
```

### Single-query join

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

## Maintenance

```sh
php maintenance/run.php SaintapediaGraph:importPages [--dry-run] [--overwrite] [--examples]
```

Catalog of imported pages: `includes/Maintenance/PageCatalog.php` (unit-tested without MediaWiki). `--examples` is opt-in (OrgDemo / GrantDemo + demo page). The mediawiki.org listing is a paste file only: `docs/mediawiki.org-Extension-SaintapediaGraph.wikitext` — do not import it onto the target wiki.

## CSP and languages

The renderer loads **only** the same-origin ResourceLoader module `ext.saintapediaGraph` (PHP + bundled `mermaid.min.js`). No CDN.

If the wiki sets a CSP:

- `script-src` must allow the wiki origin (ResourceLoader).
- `style-src` should allow `'unsafe-inline'` **or** the wiki origin — Mermaid 10 writes some SVG presentation styles.
- Do not add `cdn.jsdelivr.net` / `unpkg.com` for this extension.

A CSP that forbids inline style may leave diagrams unstyled. That is a wiki policy choice.

UI strings are English-only. Other `$wgLanguageCode` values still show English errors and warnings. Extra languages can be added as `i18n/<code>.json`; there is no translatewiki.net project yet.

## Tests and smoke

Standalone PHPUnit (no MediaWiki core / Cargo):

```sh
composer install
vendor/bin/phpunit
```

Or: `docker run --rm -v "$PWD":/app -w /app php:8.2-cli php vendor/bin/phpunit`

CI runs that suite on PHP 8.1, 8.2, and 8.3.

After `wfLoadExtension( 'SaintapediaGraph' )` on a wiki:

1. `Special:Version` shows **0.2.5** and `ext.saintapediaGraph`.
2. `php maintenance/run.php SaintapediaGraph:importPages` (add `--overwrite` if pages exist; add `--examples` for OrgDemo/GrantDemo).
3. Confirm Help + both templates (and, with `--examples`, `Help:Saintapedia Graph/Examples`).
4. Render an org chart and a dual-table funding graph.
5. Click a node — stay on-wiki.
6. `theme=forset` still renders and warns.
7. `limit=2000` and `limit=-5` warn (`reduced to` the cap or `1`).
8. Hard-refresh (Ctrl+Shift+R) after updates.

Local Canasta (`localhost:8080`) was used for the 0.2.5 smoke (`antiscript` clicks, `Saintapedia Graph demo`, `Saintapedia Graph smoke`).

## License

- Extension: GPL-2.0-or-later (`COPYING`)
- Bundled Mermaid: MIT — see `THIRD-PARTY.md`
