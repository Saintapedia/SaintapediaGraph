# Saintapedia Graph

MediaWiki extension that turns [Extension:Cargo](https://www.mediawiki.org/wiki/Extension:Cargo) data into interactive Mermaid flowcharts — org charts, funder→fundee networks, styled/grouped graphs, and dual-table diagrams.

| | |
|---|---|
| **Version** | 0.2.0 |
| **Directory** | `extensions/SaintapediaGraph` |
| **Parser functions** | `{{#saintapedia_graph:…}}`, alias `{{#cargo_mermaid:…}}` |
| **Requires** | MediaWiki 1.39+, PHP 8.1+, **Cargo** |
| **Mermaid** | **Bundled** (`resources/lib/mermaid.min.js`) — Extension:Mermaid is **not** required |
| **Help** | `docs/Help-Saintapedia_Graph.wikitext` → `Help:Saintapedia Graph` |
| **Templates** | `templates/Org_chart.wikitext`, `templates/Funding_network.wikitext` |

## Install

1. Place this folder at `extensions/SaintapediaGraph` (name must match ResourceLoader path).
2. After Cargo in `LocalSettings.php` / Canasta `settings.yaml`:

```php
wfLoadExtension( 'Cargo' );
wfLoadExtension( 'SaintapediaGraph' );
```

3. Check `Special:Version`.
4. Optional: import help + templates from `docs/` and `templates/`.

## Configuration

| Variable | Default | Meaning |
|----------|---------|---------|
| `$wgSaintapediaGraphDefaultDirection` | `TD` | `TD` / `LR` / `BT` / `RL` |
| `$wgSaintapediaGraphDefaultLimit` | `500` | Default `limit=` |
| `$wgSaintapediaGraphMaxLimit` | `1000` | Hard cap on rows per query |
| `$wgSaintapediaGraphClickable` | `true` | Nodes link to wiki pages |
| `$wgSaintapediaGraphDefaultTheme` | `default` | Mermaid theme (also embedded in diagram source via `%%{init}%%`) |
| `$wgSaintapediaGraphUseStandaloneRenderer` | `true` | Interactive render (false = raw source only) |
| `$wgSaintapediaGraphWarnCycles` | `true` | Warn when directed cycles exist |
| `$wgSaintapediaGraphBreakCycles` | `false` | Drop cycle-closing edges |
| `$wgSaintapediaGraphStylePalette` | 10 colors | Fills for `style_by` |

Theme is applied in the generated Mermaid source (`%%{init: {theme:…}}%%`). The HTML `data-theme` attribute mirrors that for debugging; rendering uses the source init block.

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

`style=` / `subgraph=` values must be real Cargo column names.

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

## License

- Extension: GPL-2.0-or-later (`COPYING`)
- Bundled Mermaid: MIT — see `THIRD-PARTY.md`
