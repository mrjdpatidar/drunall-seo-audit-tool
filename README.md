# Drunall SEO Audit & Site Speed Checker

Free **SEO audit tool**, **site speed audit**, and **technical SEO checker** for websites.  
Audit any public URL for Google ranking signals, Core Web Vitals (FCP, LCP, TBT, CLS, Speed Index), sitemap SEO crawl, SSL/HTTPS, meta tags, alt text, H1, external links, scripts, and technology detection.

**Live demo:** [http://app.drunall.com/audit-tool](http://app.drunall.com/audit-tool)  
**Brand:** [Drunall](https://drunall.com/)

---

## Keywords / topics

`SEO audit` · `free SEO checker` · `site speed audit` · `PageSpeed Insights` · `Core Web Vitals` · `FCP` · `LCP` · `TBT` · `CLS` · `Speed Index` · `technical SEO` · `sitemap crawl` · `broken links` · `SSL HTTPS check` · `meta title description canonical` · `Google ranking` · `website health` · `React Vite Shopify WordPress detection` · `PHP SEO tool`

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

## Stack

- Frontend: HTML / CSS / vanilla JS  
- Backend: PHP 8+ (`api/*.php`)  
- Needs: `curl`, JSON, writable `storage/`  
- Optional local Node helper: `server.mjs`
---

## Privacy

- Reports stay in the browser (`localStorage`).  
- Audited public URLs may be counted on the server (`storage/usage.json`: domain, url, count, first/last used).  
- Server also stores short-lived rate-limit + PageSpeed cache files.  
- No email signup required.

---

## License

© [Drunall](https://drunall.com/).
