# Filmoteca Pro

Filmoteca Pro is a native PHP 8+ application that delivers an SEO-first movie catalog and community hub. It couples an accessible Bulma-powered UI with robust TMDb integrations, structured data, and production-ready best practices.

## Quick Start

1. **Requirements**
   - PHP 8.1+
   - Composer (optional, no dependencies required initially)
    - MySQL 8 (or MariaDB 10.5+)
    - Extensions: cURL, GD (per poster conversion), mbstring, json
2. **Clone & Configure**
   ```bash
   git clone <repo-url> filmoteca
   cd filmoteca
   cp .env.example .env
   ```
   Fill `.env` with your environment, database, and TMDb credentials. Restrict file permissions (`chmod 600 .env` on Unix systems).
3. **Set Up Database**
   - Create a dedicated DB user with least privileges and TLS enforced.
   - Run the SQL scripts in `scripts/db/movies_schema.sql` and `scripts/db/movies_seed.sql`.
4. **File Permissions**
   - Allow web server write access to `cache/`, `assets/posters/cache/`, and `logs/` (if created).
5. **Serve**
  - Local: `php -S localhost:8000 router.php`.
   - Production: configure Apache/Nginx with rewrite rules in `docs/rewrite` section below.
6. **Verify**
   - Home: `http://localhost:8000/`
   - Film detail: `http://localhost:8000/film/1/inception-2010`
   - API: `http://localhost:8000/api/movies.php`

## Architecture Overview

```
├── index.php               // Front controller + home listing
├── film.php                // Movie detail page renderer
├── header.php / footer.php // Shared layout includes
├── movie-card.php          // Reusable movie card component
├── movies.php              // Seed dataset + helpers
├── api/movies.php          // Public JSON API
├── lib/                    // Core helpers (env, cache, db, seo, tmdb, utils)
├── assets/                 // Bulma overrides, JS, posters
├── cache/                  // File-based cache for TMDb + API
├── scripts/cli/            // CLI tooling (sync, poster fetch)
├── scripts/db/             // SQL schema & seed
├── sitemap.php             // Dynamic sitemap generator
├── robots.txt              // Robots policy
└── README.md               // This document
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database connectivity |
| `APP_ENV` | `development` or `production` |
| `APP_URL` | Canonical site URL (no trailing slash) |
| `CACHE_TTL` | Default TTL (seconds) for page/API cache |
| `TMDB_API_KEY` | TMDb v3 API key |
| `TMDB_READ_ACCESS_TOKEN` | TMDb v4 read token (Bearer) |
| `TMDB_USE_REMOTE_IMAGES` | `true` to use TMDb CDN directly |
| `TMDB_CACHE_TTL` | TTL for TMDb cache bucket |
| `ADMIN_USERNAME`, `ADMIN_PASSWORD` | Admin login credentials (plain text) |
| `ADMIN_HASHED_PASSWORD` | Optional bcrypt hash generated via `password_hash` |

Never expose TMDb keys client-side. Rotate credentials regularly and store secrets in a vault in production.

## Database Schema

See `scripts/db/movies_schema.sql` for a normalized table compatible with TMDb sync. Example seed data is in `scripts/db/movies_seed.sql`. The schema includes metadata for local and remote poster management, cast JSON, and SEO fields.

### Minimum Privileges

```sql
CREATE USER 'filmoteca'@'%' IDENTIFIED BY 'strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON filmoteca_pro.* TO 'filmoteca'@'%';
FLUSH PRIVILEGES;
```

- Enforce TLS/SSL connections.
- Restrict to specific CIDR ranges when possible.

## TMDb Integration

- `lib/tmdb.php` manages authentication, retries, rate limiting, and caching.
- Responses are cached to `cache/tmdb/<hash>.json` for `TMDB_CACHE_TTL` seconds.
- Poster downloads via CLI script store WebP variants under `assets/posters/cache/<size>/` and populate DB fields.
- Footer displays required TMDb attribution text.

### Usage Flow

1. Search `searchMovie()` to map titles to TMDb IDs.
2. Fetch metadata with `getMovie($id, 'credits,videos,images')`.
3. Cache responses and optionally download posters.
4. Update database via `scripts/cli/tmdb_sync.php`.

Respect TMDb rate limits (40 req/10s). Configure `TMDB_CACHE_TTL` >= 24h for metadata heavy endpoints.

## Admin Area

- `/admin/login.php` session-based login with CSRF and hashed password support.
- `/admin/dashboard.php` metrics (totale film, generi, ultimi aggiornamenti) e scorciatoie operative.
- `/admin/import_csv.php` consente import massivo; valida colonne, usa transazioni quando MySQL è disponibile e salva anteprime JSON se in modalità seed.
- `/admin/upload_poster.php` genera varianti WebP (o JPG fallback) e aggiorna `poster_path_local`.
- `/admin/logout.php` rigenera la sessione e invalida il cookie.

Proteggi l'area admin dietro VPN o IP allowlist e abilita HTTPS obbligatorio con cookie `SameSite=Strict` (già impostato da `lib/auth.php`).

## API Contract

`GET /api/movies.php`

Query params: `search`, `genre`, `year`, `page`, `per_page`, `tmdb`.

Response shape:
```json
{
  "data": [
    {
      "id": 1,
      "title": "Inception",
      "slug": "inception-2010",
      "year": 2010,
      "genre": "Science Fiction",
      "summary": "A thief who steals corporate secrets...",
      "poster": {
        "url": "https://.../inception.webp",
        "srcset": [
          {"size": "w342", "url": "..."}
        ],
        "placeholder": "data:image/jpeg;base64,..."
      },
      "rating": 8.3,
      "aggregateRating": {
        "ratingValue": 8.3,
        "ratingCount": 32000
      }
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 12,
    "total": 24,
    "total_pages": 2
  },
  "links": {
    "self": "https://filmoteca.test/api/movies.php?page=1",
    "prev": null,
    "next": "https://filmoteca.test/api/movies.php?page=2"
  }
}
```

Headers include `Cache-Control`, `ETag`, `Link`, `X-RateLimit-Remaining` (best-effort).

## SEO & Structured Data

- Server-rendered meta tags per page via `lib/seo.php`.
- JSON-LD payloads:
  - Homepage: `WebSite`, `Organization`, `SearchAction`.
  - Film detail: `Movie`, `VideoObject` (when trailer), `BreadcrumbList`.
- `sitemap.php` emits XML with canonical URLs, lastmod, and optional `<image:image>` entries.
- `robots.txt` references sitemap and disallows `/admin` and protected APIs.
- Replace `/assets/images/icon.svg` and provide a 1200x630 social preview asset referenced in `seo_default_meta()` before launch.
- OG/Twitter meta use poster/social preview. Fallback to generated social card instructions in docs.

## Progressive Enhancements

- Bulma 1.0.4 via CDN, custom CSS under `assets/css/styles.css`.
- Dark mode toggle uses `localStorage` and `prefers-color-scheme` fallback.
- Search debounce and filter chips handled in `assets/js/main.js`.
- Lazy loading images with `loading="lazy"` + intersection observer fallback.
- Accessibility: proper aria labels, semantic landmarks, focus traps for modals.

## CLI Tooling

- `scripts/cli/tmdb_sync.php`: nightly sync orchestrator (idempotent, supports delta updates).
- `scripts/cli/poster_fetch.php`: bulk download posters + WebP generation.
- Both scripts expose `--help` usage and exit codes for cron integration.

## Rewrite Rules

### Apache (`.htaccess`)

```
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ index.php [QSA,L]
</IfModule>
```

### Nginx

```
location / {
  try_files $uri $uri/ /index.php?$args;
}
```

Ensure PHP-FPM fastcgi params include `SCRIPT_FILENAME` for front controller routing.

## Deployment Checklist

- [ ] Update `.env` with production credentials (no defaults).
- [ ] Force HTTPS and configure HSTS.
- [ ] Configure reverse proxy caching for `/api` and `/sitemap.xml`.
- [ ] Upload social preview images or enable generator CLI.
- [ ] Update `robots.txt` sitemap URL with the production domain.
- [ ] Run `php scripts/cli/tmdb_sync.php --mode=full`.
- [ ] Warm caches via `curl` on homepage, top film pages, API.
- [ ] Lighthouse: FCP < 1s, LCP < 2.5s, CLS < 0.1.
- [ ] Validate JSON-LD (Google Rich Results Test).
- [ ] Submit `sitemap.xml` to Google Search Console and Bing Webmaster.
- [ ] Configure monitoring/alerting for API errors and TMDb rate limit responses.

## Testing & Monitoring

- Use `composer require --dev squizlabs/php_codesniffer` for linting (optional).
- Run unit tests (TBD) for helpers and API responses.
- Monitor Core Web Vitals via CrUX/Looker Studio.
- Log TMDb errors with structured logs and rotate.

## Roadmap

- Admin dashboard enhancements: multi-role access, review moderation, audit log.
- PWA manifest + offline cache for watchlists.
- Background workers for cache invalidation and sitemap refresh.
- Social preview image generator (GD/Imagick).
- Multi-language support with hreflang.

## License & Compliance

- Respect TMDb [Terms of Use](https://www.themoviedb.org/documentation/api/terms-of-use).
- Include footer attribution: “This product uses the TMDb API but is not endorsed or certified by TMDb.”
- Rotate keys periodically; never expose TMDb secrets in client bundles.
