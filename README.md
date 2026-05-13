# Portfolio

Personal portfolio site built on Drupal 11. The site serves two purposes: professional presentation (bio, projects, skills, articles) and a living technical showcase where each major feature is implemented using a different Drupal pattern — the codebase itself is the portfolio.

## Tech Stack

- **CMS:** Drupal 11
- **PHP:** 8.3
- **Database:** MariaDB 11.8
- **Local environment:** DDEV
- **Theme:** Custom (`portfolio_theme`) — no base theme, SDC components, Dart Sass, BEM
- **Admin theme:** Gin
- **Search:** Search API + Database backend + Facets
- **CSS:** Custom properties (design tokens), no frameworks

## Local Setup

### Requirements

- [DDEV](https://ddev.com/) v1.23+
- Docker

### Install

```bash
git clone git@github.com:Ricardo0597/portfolio.git
cd portfolio
ddev start
ddev composer install
ddev drush site:install --existing-config -y
ddev drush cr
```

Site will be available at **https://portfolio.ddev.site**.

Default admin credentials: `admin` / `admin`

## Common Commands

```bash
# Cache
ddev drush cr

# Config
ddev drush cex -y       # export config after structural changes
ddev drush cim -y       # import config

# Theme build (Dart Sass)
ddev theme-build        # install deps, compile SCSS, rebuild cache
ddev theme-watch        # watch mode for SCSS

# Expose site publicly (ngrok)
ddev share              # expose site via ngrok tunnel, prints a public URL
```

## Project Structure

```
portfolio/
├── config/sync/                    # Drupal config (source of truth)
├── web/
│   ├── modules/custom/
│   │   └── portfolio_core/         # single custom module
│   │       ├── src/Plugin/Block/   # CurrentSkillsBlock
│   │       ├── src/Plugin/rest/    # Projects REST endpoint
│   │       ├── src/EventSubscriber/# Profile redirect
│   │       └── src/TwigExtension/  # skill_level_label, reading_time
│   └── themes/custom/
│       └── portfolio_theme/
│           ├── components/         # SDC components
│           │   ├── project-card/
│           │   ├── skill-card/
│           │   ├── article-teaser/
│           │   └── feature-badge/
│           └── scss/               # Dart Sass source
└── .ddev/
    └── commands/                   # custom DDEV commands
```

## Drupal Patterns Demonstrated

Each feature is an intentional showcase of a different Drupal pattern:

| Feature | Pattern |
|---|---|
| Featured skills block | Block Plugin + Dependency Injection |
| `/api/portfolio/projects` | REST Resource Plugin + CacheableJsonResponse |
| `skill_level_label`, `reading_time` Twig filters | Twig Extension service |
| Project search + filters | Search API + Facets + Views |
| Project/Article listings | Preprocess hooks + SDC view mode templates |
| SEO metadata | Metatag + Simple Sitemap config |
| Headless pattern | JSON:API with filters, sparse fieldsets, includes |
| PDF CV export | Entity Print + dompdf + `pdf` view mode + preprocess hook |
| Structured data (node pages) | `hook_page_attachments` + JSON-LD (`SoftwareSourceCode`, `Article`) |
| Structured data (listing pages) | `hook_views_post_render` + JSON-LD `ItemList` |
| Light/dark mode toggle | CSS custom property token swap + `localStorage` persistence |
| Mobile navigation | Animated hamburger drawer + `aria-expanded` + Drupal behavior |

## Content Types

| Type | Path pattern |
|---|---|
| Profile | `/about` |
| Project | `/projects/[title]` |
| Skill | `/skills/[title]` |
| Article | `/articles/[title]` |
| Feature Demo | `/features/[title]` |

## Development Notes

- All structural changes require a config export before committing: `ddev drush cex -y`
- Never run `drush` or `composer` outside DDEV
- Stage files explicitly — no `git add .`
- One custom module only: `portfolio_core`
- Commit format: [Conventional Commits](https://www.conventionalcommits.org/)
- All component colours must use `var(--color-*)` tokens — the light/dark toggle swaps nine CSS custom properties at runtime; hardcoded colours break light mode. See CLAUDE.md for full SCSS patterns.
