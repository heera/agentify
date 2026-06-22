# Asset-generation sources (dev only)

Scripts and fixtures used to regenerate the WordPress.org **listing assets** in
`../../.wordpress-org/` (the icon, banner and screenshots shown on the plugin
page). This whole `.dev/` directory is excluded from the published plugin via
`.distignore`, so nothing here ships to users — it lives in the repo only so the
listing assets stay reproducible and in one place.

The listing images themselves live in `../../.wordpress-org/`:
`icon-128x128.png` / `icon-256x256.png` / `icon-512x512.png`,
`banner-772x250.png` / `banner-1544x500.png`, and
`screenshot-1.png` … `screenshot-6.png`. Those are what CI publishes to SVN.

## Regenerate the screenshots

They are real renders of the built admin app — not mockups — so the build must
exist first.

```bash
# from the plugin root:
npm run build                 # builds assets/admin/app.{js,css}
npm i -D puppeteer-core        # one-off; drives your installed Chrome (not committed)
node .dev/asset-sources/render-screenshots.mjs
```

Writes `screenshot-1.png` … `screenshot-6.png` into `../../.wordpress-org/`.
Chrome elsewhere / non-macOS:
`CHROME=/path/to/chrome node .dev/asset-sources/render-screenshots.mjs`.

The 6 shots, in `readme.txt` order:
1. Dashboard · 2. Settings (Identity + Security + Features) · 3. Readiness ·
4. Discovery · 5. Crawler policy + Block scanners · 6. "Activity to review" bell.

### Pieces
- **`render-screenshots.mjs`** — opens each tab via the URL hash, clips each
  section (and clicks the bell open for shot 6), 1440px wide. Self-contained:
  paths are derived from its own location.
- **`harness.html`** — loads the built app via relative paths and stubs the
  `/activity` REST call from `window.__ACTIVITY__`.
- **`data.js`** — the sample fixture. Edit it to change what the shots show:
  `AgentimusData` (identity, readiness, discovery), `__ACTIVITY__` (the log),
  `__ACTIVITY__.threats` (drives the nav review-bell in shots 5/6), and the
  `block_agents`/`block_spoofed`/`blocked_agents`/`allowed_agents` settings
  (populate the Settings crawler section in shot 5).

## Regenerate the banner
`banner.html` rendered headless at 1544×500, then downscaled to 772×250 → the two
`banner-*.png`.

## Author-local helpers (optional)
`dump_bootstrap.php` (dump the live `Admin::bootstrap_data()` to seed `data.js`),
`gen_activity.py` (build a sample activity payload), `trim.py` (crop trailing
background), `enrich_identity.php` / `restore_identity.php` (temporarily fill the
local identity for a *live* capture, then revert). The `render-screenshots.mjs`
+ `data.js` path above is self-contained and preferred over these.
