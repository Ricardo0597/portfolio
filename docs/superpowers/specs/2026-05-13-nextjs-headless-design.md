# Design: Next.js Headless Layer — Tech Constellation + AI Portfolio Search

**Date:** 2026-05-13
**Status:** Approved

---

## Goal

Add a `/headless` section to the portfolio served by a Next.js app running alongside Drupal in the same DDEV environment. The section demonstrates two things that are not achievable in Drupal's traditional render pipeline:

1. **Tech Constellation** — an interactive D3.js force-directed graph visualising the relationships between technologies across projects and skills
2. **AI Portfolio Search** — natural language search powered by Transformers.js running entirely in the browser (no API cost, no server)

Both features consume data exclusively from Drupal's existing JSON:API. No new Drupal modules are required.

---

## Repositories

| Repo | Purpose |
|------|---------|
| `portfolio` (this repo) | Drupal site — provides JSON:API data, DDEV config, nginx proxy |
| `portfolio-headless` (new, separate GitHub repo) | Next.js app — cloned locally into `nextjs/` (gitignored in portfolio repo) |

The separation mirrors real headless Drupal engagements (separate frontend and backend teams/repos) and makes the Next.js app show as a standalone GitHub project.

---

## Architecture

```
portfolio.ddev.site/           → DDEV nginx → Drupal (PHP-FPM)
portfolio.ddev.site/headless/* → DDEV nginx → Next.js container (port 3000)
```

When `ddev share` runs, ngrok tunnels to DDEV's nginx. Both Drupal and Next.js are exposed under the same public URL automatically — no second tunnel needed.

### DDEV additions (in portfolio repo)

**`.ddev/docker-compose.nodejs.yaml`**
Defines a `nodejs` service that mounts `./nextjs` and runs `npm run dev` on port 3000. The service joins the DDEV network so nginx can reach it by hostname.

**`.ddev/nginx_full/nextjs-proxy.conf`**
Adds a `location /headless` block that `proxy_pass`es to `http://nodejs:3000`. Strips no path prefix — Next.js handles it via `basePath`.

### Next.js configuration

- `basePath: '/headless'` in `next.config.js` — all routes and assets are automatically prefixed
- `output: 'standalone'` for production builds (future deployment)
- API calls target `https://portfolio.ddev.site` in local dev, configurable via `NEXT_PUBLIC_DRUPAL_BASE_URL` env var

### Drupal changes

- One new menu item: label "Headless", path `/headless`, added to the main navigation
- No new modules, no config export needed beyond the menu item

---

## Pages & Routes

### `/headless` — Landing page

Entry point. Two feature cards linking to `/headless/constellation` and `/headless/search`. A persistent top banner reads:

> *"You're in the headless layer — data served by Drupal JSON:API, rendered by Next.js."*

The banner has a subtle green pulse animation to signal it is alive and dynamic.

### `/headless/constellation` — Tech Constellation

Full-viewport D3.js force-directed graph. An info badge in the corner reads:

> *"Rendering: CSR · All data fetched once from Drupal JSON:API · Filtered and visualised client-side."*

**Interaction:**
- Click a node → graph freezes, node is highlighted, connected nodes stay visible, unconnected nodes fade to 20% opacity, a side panel slides in from the right listing related projects
- Click background → resets to full graph
- Scroll/pinch → zoom
- Drag nodes → reposition (D3 simulation resumes on release)

### `/headless/search` — Ask My Portfolio

Centred search input. A loading indicator reads *"Loading knowledge base…"* while Transformers.js initialises and embeds the content. Once ready, natural language queries return a ranked list of matching projects and skills with scores. An info badge reads:

> *"Rendering: CSR · Embeddings computed in your browser · No API calls, no server, no cost."*

---

## Visual Identity

Intentionally distinct from the portfolio's dark navy palette to make the technology boundary visible.

| Token | Value | Rationale |
|-------|-------|-----------|
| Background | `#000000` | Pure black — maximum contrast, signals different environment |
| Surface | `#111111` | Cards and panels |
| Primary text | `#ffffff` | High contrast on black |
| Accent | `#00ff87` | Terminal green — contrasts with portfolio's `#e94560` red |
| Font | `JetBrains Mono` (already loaded) | Reinforces "developer tool" feel |
| Border radius | `0px` (sharp corners) | vs portfolio's `8px` — another signal of a different design system |
| Border | `1px solid #1a1a1a` | Subtle surface separation |

The top banner uses the green accent with a `2s ease-in-out infinite` opacity pulse.

---

## Data Flow

### JSON:API endpoints consumed

```
GET /jsonapi/node/project
  ?include=field_stack,field_status
  &filter[status]=1
  &fields[node--project]=title,field_summary,field_stack,path

GET /jsonapi/node/skill
  ?include=field_category
  &filter[status]=1
  &fields[node--skill]=title,field_skill_level,field_category,path

GET /jsonapi/taxonomy_term/technology_stack
  &fields[taxonomy_term--technology_stack]=name
```

All three requests are made on page load for both `/constellation` and `/search`. Data is fetched once, stored in React state, and never re-fetched during the session.

### Constellation data model

Each technology node carries:
- `id` — taxonomy term UUID
- `name` — term label
- `weight` — `(project_count × 2) + skill_level` where `skill_level` is 0 if not in skills, 1–5 if it is. Node radius scales with weight.
- `category` — skill category label (from `field_category`), used for colour encoding. Technologies not in the skill list default to category `"Other"`

Each edge carries:
- `source` / `target` — technology node IDs
- `strength` — number of projects where both technologies appear together. Edge thickness scales with strength.

### Search data model

Each searchable item carries:
- `id`, `type` (`project` | `skill`), `title`, `summary`, `path`
- `embedding` — Float32Array generated by Transformers.js from `title + ". " + summary`

Cosine similarity between the query embedding and each item embedding produces the ranked result list. Top 5 results are displayed.

---

## Tech Constellation — Implementation Detail

**Library:** D3.js v7 (`d3-force`, `d3-selection`, `d3-zoom`, `d3-scale-chromatic`)

**Force simulation parameters:**
- `forceLink` — edges, distance proportional to `1 / strength` (strong co-occurrence = closer)
- `forceManyBody` — repulsion strength `-200`
- `forceCenter` — centres on viewport midpoint
- `forceCollide` — radius = node radius + 4px (prevents overlap)

**Colour scale:** `d3.scaleOrdinal(d3.schemeTableau10)` mapped to skill categories. Consistent across renders.

**Side panel:** A fixed-position React panel (not a D3 element). Populated from the pre-fetched project data when a node is clicked. Contains project title (linked), status badge, and stack tags.

**Responsive:** The SVG uses `viewBox` and fills its container. On mobile the side panel becomes a bottom sheet.

---

## AI Search — Implementation Detail

**Model:** `Xenova/all-MiniLM-L6-v2` via `@xenova/transformers`
- Size: ~23MB (downloaded once, cached in browser via Cache API)
- Runs entirely in-browser via WebAssembly
- No API key, no server, no ongoing cost

**Embedding strategy:**
- Input text per item: `"{title}. {summary}"` — title first for stronger signal
- Embeddings are generated in a `useEffect` after data fetch, with a progress counter shown to the user
- Stored in a `useRef` to avoid re-embedding on re-renders

**Search:**
1. Query text is embedded using the same model
2. Cosine similarity computed against all item embeddings
3. Items sorted by score descending, top 5 displayed
4. Results show: type badge (`Project` / `Skill`), title (linked to Drupal page), summary excerpt, similarity score as a percentage

**Loading state:** Three-phase indicator — *"Fetching content…"* → *"Loading AI model…"* → *"Building knowledge base… (N/M)"* → *"Ready"*

---

## Next.js Project Structure

```
portfolio-headless/                 ← separate GitHub repo
├── next.config.js                  ← basePath: '/headless'
├── package.json
├── .env.local.example              ← NEXT_PUBLIC_DRUPAL_BASE_URL=https://portfolio.ddev.site
├── src/
│   ├── app/
│   │   ├── layout.js               ← root layout, banner, global styles
│   │   ├── page.js                 ← /headless landing
│   │   ├── constellation/
│   │   │   └── page.js             ← /headless/constellation
│   │   └── search/
│   │       └── page.js             ← /headless/search
│   ├── components/
│   │   ├── Banner.js               ← persistent top banner
│   │   ├── Constellation.js        ← D3 graph component
│   │   ├── ConstellationPanel.js   ← slide-in project panel
│   │   ├── SearchBox.js            ← search input + results
│   │   └── InfoBadge.js            ← rendering strategy badge
│   ├── hooks/
│   │   ├── usePortfolioData.js     ← fetches + normalises JSON:API data
│   │   └── useEmbeddings.js        ← loads Transformers.js, embeds items
│   └── styles/
│       └── globals.css             ← CSS custom properties, reset
```

---

## DDEV Integration

### `portfolio` repo additions

```
.ddev/
  docker-compose.nodejs.yaml      ← nodejs service definition
  nginx_full/
    nextjs-proxy.conf             ← proxy /headless → nodejs:3000
nextjs/                           ← gitignored, clone of portfolio-headless here
```

**`.ddev/docker-compose.nodejs.yaml`**
```yaml
services:
  nodejs:
    image: node:20-alpine
    working_dir: /app
    volumes:
      - ./nextjs:/app
    command: sh -c "npm install && npm run dev"
    expose:
      - "3000"
    environment:
      - NEXT_PUBLIC_DRUPAL_BASE_URL=https://portfolio.ddev.site
    networks:
      - ddev_default
```

**`.ddev/nginx_full/nextjs-proxy.conf`**
```nginx
location /headless {
  proxy_pass http://nodejs:3000;
  proxy_http_version 1.1;
  proxy_set_header Upgrade $http_upgrade;
  proxy_set_header Connection 'upgrade';
  proxy_set_header Host $host;
  proxy_cache_bypass $http_upgrade;
}
```

---

## Out of Scope (this phase)

- ISR or SSR rendering strategies (intentionally CSR to demonstrate client-side capability; ISR/SSR can be a Phase 2)
- Draft preview mode (requires `drupal/next` module and additional auth setup)
- Articles in the AI search (projects + skills only)
- Production deployment of the Next.js app
- Scrollytelling build log (separate future feature)
- Authentication or personalisation

---

## Testing

Manual verification after implementation:

1. `ddev start` → visit `https://portfolio.ddev.site/headless` → landing page loads with correct visual identity
2. `ddev share` → visit ngrok URL + `/headless` on phone → same result
3. Constellation: all nodes appear, clicking a node shows the side panel with correct projects
4. Constellation: edge thickness varies (thicker for technologies that co-occur often)
5. Search: loading phases display correctly, query returns ranked results
6. Search: results link back to correct Drupal node pages
7. Drupal nav: "Headless" menu item appears and navigates correctly in both dark and light mode
