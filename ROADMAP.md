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

## v0.2 — current

- [x] **`style_by`** — node colors by field value
- [x] **`subgraph_by`** — group nodes by field value
- [x] **Dual-table mode** — `nodes_table` + `edges_table`
- [x] **Cycle detection** — `warn_cycles` / `break_cycles`
- [x] **Domain templates** — `Template:Org chart`, `Template:Funding network`
- [x] Help page + demo page on dev wiki

## Post-v0.2

### Next most useful for Saintapedia

1. **Cargo `format=mermaid`** on `#cargo_query`
2. **Weighted edges** (funding amount → thickness or label emphasis)
3. **Special page builder** for non-technical editors
4. **Lua** `mw.ext.saintapediaGraph` for custom modules
5. **Export** SVG/PNG + raw Mermaid
6. **Focus / filter** (N degrees from a node, year sliders)
7. **Cycle path labels** using human node names (not only IDs)

### Engineering / release hygiene

- [ ] CI: PHPUnit on MW 1.39 / 1.43
- [ ] Git repo + tag releases (v0.1.x)
- [ ] Vendor Mermaid license note (MIT) in COPYING / third-party
- [ ] i18n beyond English
- [ ] Production CSP docs (bundled JS is same-origin — good)
- [ ] Smoke test script against Canasta sandbox + dev
- [ ] Help page shipped as `docs/` + optional maintenance import

### Later

- Sankey for funding flows, mindmap, multi-hop expand
- Hover tooltips with extra Cargo fields
- Permission-aware hiding of sensitive amounts
- Caching / progressive load for large graphs
- Logos as node images when stored in Cargo
