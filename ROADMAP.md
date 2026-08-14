# Saintapedia Graph — roadmap

## Name

**Saintapedia Graph** (extension: `SaintapediaGraph`)

Other candidates considered: Relations, OrgViz, Network, LinkMap, Flow, Connect, CargoMermaid.

## v0.1 — MVP

- [x] `#saintapedia_graph` + `#cargo_mermaid` alias
- [x] Cargo query params (`tables`, `fields`, `where`, `join on`, `order by`, `limit`)
- [x] Parent mode (org charts)
- [x] Edge mode (funding / networks)
- [x] Clickable nodes → wiki pages
- [x] Mermaid ID/label escaping
- [x] Bundled Mermaid render (base64 source, same-origin)
- [x] Config: direction, max nodes, clickable default
- [x] README + examples + Cargo schemas

## v0.2 — shipped

- [x] **`style_by`** — node colors by field value
- [x] **`subgraph_by`** — group nodes by field value
- [x] **Dual-table mode** — `nodes_table` + `edges_table`
- [x] **Cycle detection** — `warn_cycles` / `break_cycles`
- [x] **Domain templates** — `Template:Org chart`, `Template:Funding network`
- [x] Help page + demo page on dev wiki

## v0.2.4 — current release

- [x] Theme allowlist + unknown-theme warning
- [x] Click tooltip/URL hardening (same-origin only)
- [x] CI: standalone PHPUnit on PHP 8.1 / 8.2 / 8.3
- [x] Vendor Mermaid license note (`THIRD-PARTY.md`)
- [x] Production CSP notes in README
- [x] Help page shipped as `docs/` (import on the wiki)
- [x] Maintenance script `SaintapediaGraph:importPages`
- [x] mediawiki.org page draft (`docs/mediawiki.org-Extension-SaintapediaGraph.wikitext`)
- [x] English-only UI documented as a known limit
- [x] Tag **v0.2.2** (do not reuse `v0.2.1`)
- [x] Tag **v0.2.3** on current `main` (docs, clamp wording, importPages)
- [ ] Tag **v0.2.4** after this release PR (detailed help + README)
- [x] Local Canasta (`localhost:8080`) smoke
- [ ] Create https://www.mediawiki.org/wiki/Extension:SaintapediaGraph from the draft
- [ ] Enable on production Saintapedia (or `dev.saintapedia.org` first if you want a remote-dev pass)

Standalone PHPUnit does **not** boot MediaWiki 1.39/1.43 or Cargo. A full MW matrix stays a later engineering item.

## Post-v0.2

### Next most useful for Saintapedia

1. **Cargo `format=mermaid`** on `#cargo_query`
2. **Weighted edges** (funding amount → thickness or label emphasis)
3. **Special page builder** for non-technical editors
4. **Lua** `mw.ext.saintapediaGraph` for custom modules
5. **Export** SVG/PNG + raw Mermaid
6. **Focus / filter** (N degrees from a node, year sliders)
7. **Cycle path labels** using human node names (not only IDs)

### Later

- i18n beyond English (translatewiki or extra `i18n/*.json` files)
- Full MediaWiki + Cargo CI (MW 1.39 / 1.43)
- Sankey for funding flows, mindmap, multi-hop expand
- Hover tooltips with extra Cargo fields
- Permission-aware hiding of sensitive amounts
- Caching / progressive load for large graphs
- Logos as node images when stored in Cargo
