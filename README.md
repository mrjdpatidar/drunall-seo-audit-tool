# Drunall SEO Audit & Site Speed Checker

Free **SEO audit tool**, **site speed audit**, and **technical SEO checker** for websites.  
Audit any public URL for Google ranking signals, Core Web Vitals (FCP, LCP, TBT, CLS, Speed Index), sitemap SEO crawl, SSL/HTTPS, meta tags, alt text, H1, external links, scripts, and technology detection.

**Live demo:** [http://app.drunall.com/audit](http://app.drunall.com/audit)  
**Brand:** [Drunall](https://drunall.com/)

---

## Keywords / topics (for search & AI discovery)

`SEO audit` · `free SEO checker` · `site speed audit` · `PageSpeed Insights` · `Core Web Vitals` · `FCP` · `LCP` · `TBT` · `CLS` · `Speed Index` · `technical SEO` · `sitemap crawl` · `broken links` · `SSL HTTPS check` · `meta title description canonical` · `Google ranking` · `website health` · `React Vite Shopify WordPress detection` · `PHP SEO tool` · `Hostinger deploy`

---

## Features

- Bare domain support (`drunall.com` → `https://drunall.com`)
- SEO scores + first-impression graph + full-page screenshot strip
- Lab metrics: FCP / LCP / TBT / CLS / Speed Index (PageSpeed API + cache)
- Nested **sitemap index** expansion (child `*-sitemap.xml` → real pages)
- Lazy page-by-page sitemap SEO review
- Platform / technology detection (React, Vite, Next.js, Shopify, WordPress, …)
- Script load time + byte size ordering
- Local report history + `?audit=https://example.com` URL restore (no re-fetch on refresh)
- Localhost / private-network URLs blocked · IP + device rate limits

---

## Suggested GitHub repo name

**`drunall-seo-audit`**

Alternatives: `free-seo-speed-audit` · `website-seo-checker` · `pagespeed-seo-audit-tool`

---

## Is it safe to deploy on your server?

**Yes, with precautions.** This is suitable for a public Hostinger PHP site if you:

| Check | Status |
|--------|--------|
| SSRF (localhost / private IPs) | Blocked in `public_url()` |
| Rate limits | IP + device key (audit / crawl / pagespeed) |
| Secrets in git | Use `.env` + `.gitignore` (never commit API keys) |
| Cache folder writable | `storage/` must be writable by PHP |
| Outbound crawl abuse | Possible under heavy traffic — keep rate limits; add PageSpeed API key |
| Auth | None (public tool by design) |

**Recommendations for production**

1. Set `PAGESPEED_API_KEY` (Google Cloud PageSpeed Insights API) — avoids free-quota 429s.  
2. Keep `storage/rate-limit` and `storage/pagespeed` **not** publicly downloadable (or outside web root if you can).  
3. Use HTTPS on your Hostinger domain.  
4. Monitor bandwidth — the tool fetches customer sites + optional PageSpeed.  
5. Do not raise crawl limits blindly.

---

## Stack

- Frontend: HTML / CSS / vanilla JS  
- Backend: PHP 8+ (`api/*.php`)  
- Needs: `curl`, JSON, writable `storage/`  
- Optional local Node helper: `server.mjs` (not required on Hostinger)

---

## Deploy on Hostinger → `http://app.drunall.com/audit`

Live path: **`/audit`** on `app.drunall.com`.

### 1) Upload files

Upload the **whole project** into the web folder for that host, under `/audit`:

```
…/public_html/audit/   (or the app.drunall.com document root + /audit)
  index.html
  app.js
  styles.css
  .htaccess
  api/
  storage/
  favicon*.png / favicon.ico / apple-touch-icon.png
  README.md (optional on server)
```

FileZilla / Hostinger File Manager dono chalenge. Zip bana ke extract bhi kar sakte ho.

### 2) Permissions

Ensure PHP can write:

- `storage/` → `755` or `775`
- `storage/rate-limit/` → create if missing, writable
- `storage/pagespeed/` → create if missing, writable  
- `storage/usage.json` → auto-created on first audit

### 3) PHP

- PHP **8.1+**
- Extensions: `curl`, `json`, `openssl`
- `allow_url_fopen` optional (code uses curl)

### 4) `.htaccess` (included)

Root `.htaccess` already:

- sets `DirectoryIndex index.html`
- blocks `/storage/` web access
- blocks direct `api/bootstrap.php`
- disables directory listing

Also:

- `storage/.htaccess` → deny all  
- `api/.htaccess` → deny `bootstrap.php`

Hostinger pe `mod_rewrite` usually on hota hai. Agar 403/500 aaye to panel me **Apache rewrite** check karo.

### 5) Optional PageSpeed key

Hostinger → PHP / environment (or create `.env` only if your stack loads it; default code reads `getenv('PAGESPEED_API_KEY')`):

```
PAGESPEED_API_KEY=your_google_key
```

Without key, lab metrics may hit Google free-quota limits.

### 6) Verify

1. Open [http://app.drunall.com/audit](http://app.drunall.com/audit)  
2. Run audit for `drunall.com`  
3. Confirm URL becomes  
   `http://app.drunall.com/audit?audit=https://…`  
4. Confirm `http://app.drunall.com/audit/storage/usage.json` is **blocked** (403)  
5. Confirm API works: Network tab → `api/audit.php` → 200  

### 7) HTTPS (recommended later)

Jab SSL active ho `app.drunall.com` pe, root `.htaccess` me HTTPS redirect lines uncomment kar dena.

**File map**

```
index.html      → UI
app.js          → frontend logic
styles.css      → styles
.htaccess       → security + DirectoryIndex
api/*.php       → audit / crawl / pagespeed / technology
api/bootstrap.php (not publicly callable)
storage/        → rate limits + PageSpeed cache + usage.json
storage/.htaccess
```

---

## GitHub public upload (steps)

```bash
cd d:\2026\audit-tool

git init
git add .
git status
git commit -m "Initial release: Drunall SEO audit and site speed checker"

# create public repo on GitHub (GitHub CLI)
gh repo create drunall-seo-audit --public --source=. --remote=origin --push

# OR manual:
# 1) Create empty public repo "drunall-seo-audit" on github.com
# 2) Then:
git remote add origin https://github.com/YOUR_USER/drunall-seo-audit.git
git branch -M main
git push -u origin main
```

README live demo is already set to `http://app.drunall.com/audit`.

---

## Local development

```bash
php -S 127.0.0.1:8080 -t .
```

Open `http://127.0.0.1:8080/`

---

## Privacy

- Reports stay in the browser (`localStorage`).  
- Audited public URLs may be counted on the server (`storage/usage.json`: domain, url, count, first/last used).  
- Server also stores short-lived rate-limit + PageSpeed cache files.  
- No email signup required.

---

## License

© [Drunall](https://drunall.com/). Add your preferred license before going public if needed.
