# Headless Drupal + Next.js — Study Guide

Everything used to build the `/headless` section of the portfolio: what each technology is, why it was chosen, how it was applied, and the concepts you should be able to discuss in an interview or portfolio presentation.

---

## Table of Contents

1. [Architecture: What "Headless CMS" Means](#1-architecture)
2. [JSON:API — Drupal as a Data Source](#2-jsonapi)
3. [Next.js 16 — The Frontend Framework](#3-nextjs)
4. [React 18 — UI Layer](#4-react)
5. [D3.js — Force-Directed Graph Visualisation](#5-d3js)
6. [Transformers.js — In-Browser AI](#6-transformersjs)
7. [WebAssembly — Running ML Models in the Browser](#7-webassembly)
8. [Nginx — Reverse Proxy and Routing](#8-nginx)
9. [Docker / DDEV — Container Orchestration](#9-docker--ddev)
10. [Semantic Search — How AI Search Works](#10-semantic-search)
11. [Key Cross-Cutting Concepts](#11-key-cross-cutting-concepts)
12. [Interview Talking Points](#12-interview-talking-points)

---

## 1. Architecture

### What is a Headless CMS?

A **headless CMS** separates the *content management backend* (the "body") from the *presentation layer* (the "head"). The CMS only handles content storage, editing, and an API. A separate frontend application fetches that data and decides how to display it.

**Traditional (coupled) Drupal:**
```
Browser → Drupal → Twig templates → HTML response
```

**Headless Drupal:**
```
Browser → Next.js → fetch → Drupal JSON:API → JSON → React renders HTML
```

### Why Go Headless?

| Concern | Traditional | Headless |
|---------|-------------|----------|
| Frontend tech | Twig + PHP | Any framework (React, Vue, etc.) |
| Interactivity | Server round-trips | Client-side (instant UI updates) |
| Real-time features | Hard (needs Ajax) | Native (React state) |
| Deployment | Monolith | Frontend and backend independently |
| Performance | PHP renders each request | Static/cached frontend + API |

### This Project's Architecture

```
Browser
  └── portfolio.ddev.site (nginx)
        ├── /           → PHP-FPM → Drupal 11 (Twig, traditional pages)
        └── /headless/* → Node.js container → Next.js 16 app
                              └── fetch /jsonapi/* → back to Drupal
```

One domain, two tech stacks, zero duplication. The Next.js app fetches data from the same Drupal instance via relative URLs — no hardcoded hostnames needed.

### Relative URLs: The Key Trick

All JSON:API fetches in the app use relative paths:
```js
fetch('/jsonapi/node/project?...')
```

Because both Drupal and Next.js sit behind the *same nginx*, this relative URL resolves correctly regardless of environment:
- Local: `portfolio.ddev.site/jsonapi/…`
- Shared via ngrok: `abc123.ngrok-free.dev/jsonapi/…`
- Production: `yoursite.com/jsonapi/…`

No environment variables, no config changes needed.

---

## 2. JSON:API

### What It Is

JSON:API is a standardised HTTP API specification for reading and writing resources. Drupal ships with a JSON:API module that exposes every content type, taxonomy vocabulary, and entity as REST endpoints automatically — no custom code required.

Spec: https://jsonapi.org

### Core Concepts

**Resource objects** — every piece of content is a JSON object with:
```json
{
  "type": "node--project",
  "id": "uuid-here",
  "attributes": { "title": "...", "field_summary": "..." },
  "relationships": { "field_stack": { "data": [...] } }
}
```

**Relationships** — references to other entities (taxonomy terms, media, etc.) via UUID.

**Includes** (`?include=field_stack`) — embed related resources in the same response, avoiding N+1 requests:
```
GET /jsonapi/node/project?include=field_stack,field_category
```

**Sparse fieldsets** (`?fields[node--project]=title,field_summary`) — return only the fields you need, reducing payload size.

**Filtering** (`?filter[status]=1`) — server-side filtering before the response is sent.

### Endpoints Used in This Project

```
GET /jsonapi/node/project
  ?include=field_stack
  &filter[status]=1
  &fields[node--project]=title,field_summary,field_stack,path

GET /jsonapi/node/skill
  ?include=field_category
  &filter[status]=1
  &fields[node--skill]=title,field_skill_level,field_category,path
```

### Drupal-Specific: Trusted Host Patterns

Drupal's `trusted_host_patterns` setting protects against HTTP Host header injection. Any domain that makes API requests must be whitelisted in `settings.php`:

```php
$settings['trusted_host_patterns'] = [
  '^portfolio\.ddev\.site$',
  '^.*\.ngrok-free\.dev$',
];
```

Without this, Drupal returns `400 Bad Request` for requests from unknown hosts (e.g., the ngrok URL on mobile).

---

## 3. Next.js 16

### What It Is

Next.js is a React framework built by Vercel. Version 16 introduced **Turbopack** as the default bundler and made several App Router improvements. It handles routing, server-side rendering, static generation, code splitting, and more.

### App Router vs Pages Router

Next.js 13+ introduced the **App Router** (`src/app/`). This project uses App Router exclusively.

| Feature | Pages Router | App Router |
|---------|-------------|------------|
| Layout system | `_app.js` | `layout.js` per segment |
| Data fetching | `getServerSideProps` | `fetch` directly in components |
| Server Components | No | Yes (default) |
| Client Components | All components | Opt-in with `'use client'` |

### Server Components vs Client Components

**Server Components** (default) render on the server only. They can fetch data, read the filesystem, access secrets. They send rendered HTML to the browser. No React state, no event handlers.

**Client Components** (`'use client'` at top of file) render on *both* server and client. The server renders the initial HTML (SSR), then React "hydrates" it on the client — attaching event listeners and making it interactive.

**Why this matters for this project:** Both the constellation and search pages are Client Components because they need:
- `useState` (for selected node, results, phase)
- `useEffect` (for data fetching, D3 simulation, model loading)
- Browser APIs (`fetch`, `window`, `document`)

### `basePath`

```js
// next.config.mjs
const nextConfig = {
  basePath: '/headless',
};
```

Tells Next.js that the app lives under `/headless/` instead of `/`. All routes, asset URLs, and Link hrefs are automatically prefixed. Inside the code, you write `<Link href="/constellation">` and Next.js renders it as `/headless/constellation`.

### Dynamic Imports

```js
const { pipeline } = await import('@xenova/transformers');
```

Dynamic imports (`import()` inside a function) load a module *on demand* instead of at app startup. Used for the AI library (23MB) so it doesn't block the initial page load — only fetched when the user navigates to the search page.

### Turbopack vs Webpack

Next.js 16 uses **Turbopack** (written in Rust) by default for both `dev` and `build`. Turbopack is much faster than webpack for HMR and compilation.

**Why we switched to `--webpack`:** The `@xenova/transformers` library has incompatibilities with Turbopack's module system. Turbopack and webpack resolve optional Node.js dependencies differently — Turbopack returned `null` for some modules that `@xenova/transformers` expected to be `{}`, causing `Object.keys(null)` errors.

The `--webpack` flag opts out of Turbopack:
```json
{ "dev": "next dev --webpack", "build": "next build --webpack" }
```

### `allowedDevOrigins`

In Next.js 16 dev mode, the HMR (Hot Module Replacement) WebSocket only accepts connections from `localhost` by default. When the app is served through a proxy (DDEV's nginx, ngrok), the browser connects from a different origin.

**What happened without this:** The WebSocket connection was blocked *during React's bootstrap (`hydrate()` call)*. Since hydration never completed, `useEffect` never ran — the page was frozen in its server-rendered initial state ("Loading constellation data…" forever).

```js
allowedDevOrigins: ['portfolio.ddev.site', '*.ngrok-free.dev'],
```

This is a dev-only concern; in production there is no HMR.

---

## 4. React 18

### Hooks Used in This Project

**`useState`** — reactive state that triggers re-renders:
```js
const [data, setData] = useState(null);
const [loading, setLoading] = useState(true);
```

**`useEffect`** — runs side effects after render (fetch, subscriptions, D3 setup):
```js
useEffect(() => {
  // run after component mounts
  fetchData();
  return () => { /* cleanup on unmount */ };
}, [deps]); // deps array controls when it re-runs
```

**`useRef`** — holds a mutable value that persists across renders without causing re-renders. Used for D3's simulation reference and selections:
```js
const simulationRef = useRef(null);
simulationRef.current = d3.forceSimulation(...);
```

**`useMemo`** — memoises a computed value, recomputed only when dependencies change:
```js
const searchItems = useMemo(() => data?.searchItems ?? [], [data]);
```
Critical in `search/page.js` — without `useMemo`, `data?.searchItems ?? []` creates a new array on every render, which makes `useEmbeddings`'s dependency check think the items changed, triggering a model reload loop.

### Hydration

**Hydration** is the process where React takes server-rendered HTML and "brings it to life" on the client — attaching event handlers, starting effects, making state reactive.

If hydration fails (mismatch between server HTML and what React expects to render), effects never run and the app is frozen in its server-rendered state.

**Hydration mismatch causes:**
- `Date.now()` or `Math.random()` used during render (different on server vs client)
- Accessing `window` or `navigator` during render (doesn't exist on server)
- External state that differs between renders

### React 18 Strict Mode

In development, React intentionally runs effects twice (mount → unmount → remount) to detect side effects that aren't properly cleaned up. The cleanup function returned from `useEffect` must cancel any in-flight work:

```js
useEffect(() => {
  let cancelled = false;
  async function load() {
    const res = await fetch('...');
    if (cancelled) return;  // component unmounted mid-fetch
    setData(await res.json());
  }
  load();
  return () => { cancelled = true; };  // cleanup
}, []);
```

---

## 5. D3.js

### What It Is

D3 (Data-Driven Documents) is a JavaScript library for binding data to DOM elements and applying data-driven transformations. Version 7 is modular — you import only what you need.

### The Force Simulation

A **force-directed graph** places nodes by simulating physical forces until the system reaches equilibrium (low energy state).

```js
d3.forceSimulation(nodes)
  .force('link',    d3.forceLink(edges).distance(100).strength(0.4))
  .force('charge',  d3.forceManyBody().strength(-600))
  .force('center',  d3.forceCenter(w/2, h/2))
  .force('x',       d3.forceX(w/2).strength(0.04))
  .force('y',       d3.forceY(h/2).strength(0.04))
  .force('collide', d3.forceCollide(radius))
```

| Force | Effect |
|-------|--------|
| `forceLink` | Edges act like springs, pulling connected nodes together |
| `forceManyBody` (negative) | Nodes repel each other (like electrons) |
| `forceCenter` | Pulls all nodes toward a single point |
| `forceX/Y` | Weak gravity toward centre axes — prevents drift |
| `forceCollide` | Nodes cannot overlap (based on radius) |

**Alpha** controls "temperature" — starts at 1.0 and decays toward 0. When alpha reaches the minimum (0.001), the simulation stops. Higher alpha = more movement.

**Tick** — every simulation step fires a `tick` event. We update DOM positions on each tick:
```js
.on('tick', () => {
  link.attr('x1', d => d.source.x).attr('y1', d => d.source.y);
  node.attr('cx', d => d.x).attr('cy', d => d.y);
})
```

**`simulation.on('end', ...)`** — fires when alpha decays to minimum. Used to zoom-to-fit:
```js
.on('end', () => {
  const bbox = g.node().getBBox();
  svg.transition().call(zoom.transform, d3.zoomIdentity...);
})
```

### Scales

**`d3.scaleSqrt`** — square root scale. Used for node radius because area (not radius) should be proportional to weight. Doubling the weight → radius increases by √2, not 2.

**`d3.scaleLinear`** — linear mapping between two ranges. Used for edge stroke width.

**`d3.scaleOrdinal(d3.schemeTableau10)`** — maps discrete categories to colours. Tableau10 is a colour scheme optimised for categorical data (distinct, readable colours).

### Zoom and Drag

```js
const zoom = d3.zoom()
  .scaleExtent([0.15, 8])
  .on('zoom', event => g.attr('transform', event.transform));
svg.call(zoom);
```

All nodes live inside a `<g>` element. Zoom transforms the entire `<g>` using CSS `transform`, which is hardware-accelerated.

Drag is applied per-node, temporarily fixing node position (`d.fx`, `d.fy`) to the mouse position.

### The Two-Effect Pattern

The constellation uses two separate `useEffect` calls:

1. **Effect 1** (`deps: [nodes, edges]`) — rebuilds the entire simulation. Runs once when data loads.
2. **Effect 2** (`deps: [selectedNode, edges]`) — updates node opacity for highlight/fade. Runs on every click without restarting the simulation.

Separating these is critical — restarting a simulation is expensive and visually jarring.

### Co-occurrence Graph (The Data Model)

Nodes = technology terms. Edges = pairs of technologies that appear together in the same project. Edge strength = how many projects share that pair.

```js
// For each project, find all pairs of its tech stack
for (let i = 0; i < stackIds.length; i++) {
  for (let j = i + 1; j < stackIds.length; j++) {
    const key = [ids[i], ids[j]].sort().join('||');
    edgeMap[key] = edgeMap[key] || { strength: 0 };
    edgeMap[key].strength++;
  }
}
```

Node weight = `(project_count × 2) + skill_level`. Technologies you use more and know better appear larger.

---

## 6. Transformers.js

### What It Is

`@xenova/transformers` is a JavaScript port of Hugging Face's Transformers library. It runs machine learning models entirely in the browser using ONNX Runtime Web (WebAssembly). No server, no API key, no inference cost.

### The Model: all-MiniLM-L6-v2

A **sentence transformer** — a neural network fine-tuned to map sentences to dense vectors (embeddings) where semantically similar sentences produce similar vectors.

- Size: ~23MB (downloaded once, cached in browser)
- Architecture: MiniLM (distilled BERT) with 6 layers
- Output: 384-dimensional float vector per input sentence
- Optimised for semantic similarity tasks

### How It's Loaded

```js
// Inside useEffect — dynamic import keeps 23MB out of initial bundle
const { pipeline } = await import('@xenova/transformers');
const embedder = await pipeline('feature-extraction', 'Xenova/all-MiniLM-L6-v2');
```

The `pipeline` function downloads the model on first use, then caches it in the browser's **Cache API** (service worker cache). Subsequent visits load from cache in milliseconds.

### Embedding Generation

```js
// Convert each item to a vector
for (const item of items) {
  const text = item.title + (item.summary ? '. ' + item.summary : '');
  const output = await embedder(text, { pooling: 'mean', normalize: true });
  embeddings.push(Array.from(output.data));
}
```

**Pooling:** A model processes tokens (word pieces), producing one vector per token. Mean pooling averages all token vectors into a single sentence vector.

**Normalisation:** Scales the vector to unit length (magnitude = 1). Required for cosine similarity to work correctly.

---

## 7. WebAssembly

### What It Is

WebAssembly (WASM) is a binary instruction format that browsers can execute at near-native speed. It's a compilation target for languages like C, C++, and Rust — enabling high-performance code in the browser without plugins.

ONNX Runtime Web (used by Transformers.js) is compiled to WASM, allowing it to run ML inference in the browser at speeds far beyond what pure JavaScript could achieve.

### Why It Matters

Before WASM, running a 23MB ML model in the browser would have been impractically slow. WASM enables:
- ML inference at near-native speed
- Cryptography, image processing, physics simulations — all in-browser
- Code sharing between server (Node.js WASM) and client (browser WASM)

### Browser Cache API

The model files are cached using the browser's **Cache API** (the same API service workers use):
```
First visit:  download 23MB → cache it
Next visits:  load from cache (~instant)
```

---

## 8. Nginx

### Reverse Proxy

Nginx acts as a **reverse proxy** — a single public-facing server that routes incoming requests to different backend services based on the request path.

```nginx
# Drupal handles everything...
location / {
  try_files $uri /index.php?$query_string;
}

# ...except /headless, which goes to Next.js
location ^~ /headless {
  proxy_pass http://nodejs:3000;
  proxy_set_header Host $host;
}
```

### Location Block Priority

Nginx location matching has a specific priority order:

1. `=` exact match (highest priority)
2. `^~` prefix match — if matched, stop checking regex
3. `~` and `~*` regex matches (in order of appearance)
4. Plain prefix match (lowest priority)

**The critical bug we fixed:** DDEV's default nginx config has:
```nginx
location ~* \.(js|css)$ {
  try_files $uri @rewrite;
}
```
This regex (`~*`) matched `/headless/_next/static/chunks/app.css` *before* the plain `location /headless` prefix match — sending CSS requests to Drupal (404) instead of Next.js.

**Fix:** Use `^~` to make the prefix match take priority over regex:
```nginx
location ^~ /headless { ... }
```
With `^~`, nginx stops checking regex locations once this prefix matches.

### WebSocket Proxying

HMR (Hot Module Replacement) uses WebSocket for live updates. Proxying WebSocket through nginx requires:
```nginx
proxy_http_version 1.1;         # WebSocket requires HTTP/1.1
proxy_set_header Upgrade $http_upgrade;    # pass the Upgrade header
proxy_set_header Connection 'upgrade';     # tell backend to upgrade
```

Without `proxy_http_version 1.1`, nginx defaults to HTTP/1.0 which doesn't support keep-alive or WebSocket.

### Variable-Based `proxy_pass`

```nginx
set $nodejs_upstream http://nodejs:3000;
proxy_pass $nodejs_upstream;
```

Using a variable for `proxy_pass` defers DNS resolution to request time (instead of server startup). Without this, if the `nodejs` container isn't running when nginx starts, nginx fails to start. The `resolver 127.0.0.11` line points to Docker's internal DNS.

---

## 9. Docker / DDEV

### DDEV Architecture

DDEV orchestrates multiple Docker containers for local development:

```
ddev-portfolio-web      nginx + PHP-FPM    (Drupal)
ddev-portfolio-db       MariaDB            (database)
ddev-portfolio-nodejs   node:20-alpine     (Next.js)
ddev-router             Traefik            (HTTPS termination, routing to containers)
```

All containers join the same Docker network (`ddev_default`), allowing them to communicate by container name (e.g., `http://nodejs:3000`).

### Custom Service

DDEV allows adding extra containers via `docker-compose.*.yaml` files:

```yaml
# .ddev/docker-compose.nodejs.yaml
services:
  nodejs:
    image: node:20-alpine
    working_dir: /app
    volumes:
      - ../nextjs:/app     # mount source code
    command: sh -c "npm install && npm run dev"
    expose:
      - "3000"
    networks:
      - ddev_default
```

Volume path `../nextjs` is relative to the `.ddev/` directory — so it mounts `./nextjs/` from the project root.

### `ddev share`

`ddev share` runs ngrok to create a public HTTPS tunnel to your local DDEV site. Because both Drupal and Next.js are behind the same nginx, a single ngrok tunnel exposes both at the same public URL — no separate tunnel for Next.js needed.

---

## 10. Semantic Search

### Keyword Search vs Semantic Search

| | Keyword Search | Semantic Search |
|--|----------------|-----------------|
| How it works | Exact word matching | Meaning/concept matching |
| "javascript frameworks" | Returns docs with those exact words | Returns docs about React, Vue, Angular |
| "help me make websites" | Might return nothing | Returns web development results |
| Requires | Index of words | Pre-computed embeddings |

### Embeddings

An **embedding** is a dense vector (array of floats) that represents the meaning of a piece of text in a high-dimensional space. Semantically related texts produce vectors that are close to each other in this space.

```
"React frontend framework"  →  [0.12, -0.34, 0.89, ...]  (384 numbers)
"Vue.js user interface"     →  [0.11, -0.31, 0.85, ...]  (similar!)
"MySQL database"            →  [-0.45, 0.23, -0.12, ...]  (different)
```

### Cosine Similarity

Measures the angle between two vectors. Returns 1.0 for identical direction (same meaning), 0 for orthogonal (unrelated), -1 for opposite.

```js
function cosineSimilarity(a, b) {
  let dot = 0, na = 0, nb = 0;
  for (let i = 0; i < a.length; i++) {
    dot += a[i] * b[i];
    na  += a[i] * a[i];
    nb  += b[i] * b[i];
  }
  const denom = Math.sqrt(na) * Math.sqrt(nb);
  return denom === 0 ? 0 : dot / denom;
}
```

The zero-denominator guard (`denom === 0`) handles zero vectors (empty text), preventing `NaN`.

### The Search Pipeline

```
1. At page load:
   - Fetch all projects + skills from Drupal JSON:API
   - Load the 23MB model (or from cache)
   - Embed each item: title + summary → 384-float vector
   
2. On each query:
   - Embed the query text → vector
   - Compute cosine similarity vs every item vector
   - Sort descending by score
   - Return top 5 results
```

**Why embed title + summary together?** The title gives strong signal about the topic; the summary provides context. Concatenating them before embedding captures both.

---

## 11. Key Cross-Cutting Concepts

### CSR vs SSR vs SSG

| Strategy | When HTML is generated | Data freshness | JS required |
|----------|----------------------|----------------|-------------|
| **SSR** (Server-Side Rendering) | On each request (server) | Always fresh | No (but hydrated) |
| **SSG** (Static Site Generation) | At build time | Stale until rebuild | No (but hydrated) |
| **CSR** (Client-Side Rendering) | In the browser | Fetched at page load | Yes |
| **ISR** (Incremental Static Regen) | At build + on-demand | Configurable | No |

This project uses **CSR intentionally** to demonstrate that D3 force simulations and ML inference can run entirely in the browser. No server involvement after the initial HTML is delivered.

### CORS (Cross-Origin Resource Sharing)

Browsers block JavaScript `fetch()` requests to a different origin (protocol + domain + port) unless the server explicitly allows it via headers.

**In this project:** All fetches use relative URLs (`/jsonapi/...`), making them *same-origin* requests from the browser's perspective. Same-origin requests never trigger CORS checks — a key advantage of the single-domain architecture.

### Graph Theory Basics

- **Node (vertex)** — an entity (technology term)
- **Edge** — a relationship between two nodes (co-occurrence in projects)
- **Weighted edge** — an edge with a numeric strength (number of projects)
- **Connected component** — a set of nodes that can all reach each other via edges
- **Degree** — how many edges a node has (highly-connected nodes are "hubs")

### Progressive Loading (UX Pattern)

The search page loads in phases and communicates each phase to the user:

```
"Fetching content…"          → JSON:API data loading
"Loading AI model…"          → Downloading/loading from cache  
"Building knowledge base…"   → Computing embeddings (N/M)
"Ready"                      → Search available
```

This prevents the user from thinking the page is broken during the 5–30 second initialisation. Each phase message is accurate — the user knows exactly what's happening.

### CSS Custom Properties for Theming

The headless layer uses a distinct visual identity to signal the technology boundary:

```css
:root {
  --bg:         #000000;  /* pure black */
  --surface:    #111111;  /* cards */
  --text:       #ffffff;
  --text-muted: #888888;
  --accent:     #00ff87;  /* terminal green */
  --border:     #1a1a1a;
}
```

CSS custom properties (variables) are resolved at runtime, enabling themes and dark mode. The deliberate choice of green (`#00ff87`) vs the Drupal portfolio's red (`#e94560`) creates an immediate visual cue: you've crossed into a different system.

---

## 12. Interview Talking Points

Use these when explaining the project to someone technical:

**"Why headless?"**
> I wanted to demonstrate capabilities that Drupal's traditional render pipeline can't do — a real-time D3 force simulation and ML inference. Both require persistent client-side state that Twig templates can't provide. Headless lets me use React for the frontend while Drupal continues doing what it does best: content management and structured data via JSON:API.

**"Why not use a backend API for the AI search?"**
> Running embeddings server-side would require either a paid API (OpenAI, Cohere) or maintaining an inference server. By using Transformers.js with a quantised MiniLM model, the entire pipeline runs in WebAssembly in the browser. Zero cost per query, zero server maintenance, and it demonstrates that modern browsers are capable of running real ML inference.

**"What was the hardest technical challenge?"**
> The nginx routing bug. DDEV's default config had a regex location block (`~* \.(js|css)$`) that matched before my prefix block (`/headless`), sending Next.js static assets to Drupal. Understanding nginx location block priority — specifically that `^~` prevents regex fallthrough — was the key insight.

**"How does the constellation data model work?"**
> I build a co-occurrence graph: technologies are nodes, and an edge exists between two technologies if they appear together in the same project. The edge weight is the number of projects sharing that pair. The D3 force simulation then treats edges like springs and nodes like charged particles — similar technologies cluster together, rarely-combined technologies drift apart.

**"What would you change for production?"**
> Switch from CSR to ISR (Incremental Static Regeneration). The Drupal data changes infrequently, so rebuilding the page every time a user loads it is wasteful. With ISR, Next.js would serve a pre-rendered page and regenerate it in the background when content changes. I'd also move the AI model loading to a Web Worker to avoid blocking the main thread.
