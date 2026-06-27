# Agentimus

[![PHP compatibility](https://github.com/heera/agentimus/actions/workflows/php-compat.yml/badge.svg?branch=main)](https://github.com/heera/agentimus/actions/workflows/php-compat.yml)
[![WordPress plugin version](https://img.shields.io/wordpress/plugin/v/agentimus?label=wordpress.org)](https://wordpress.org/plugins/agentimus/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/agentimus)](https://wordpress.org/plugins/agentimus/)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)

Make any WordPress site legible to AI agents and crawlers — `llms.txt`, a full-text
edition, markdown delivery, JSON-LD, and content-signal robots rules. Lightweight,
no SEO bloat, no framework.

**Live on WordPress.org:** <https://wordpress.org/plugins/agentimus/>

## Install

- **From your dashboard** — Plugins → Add New → search **"Agentimus"** → Install → Activate.
- **From WordPress.org** — <https://wordpress.org/plugins/agentimus/>.
- **From source** — clone this repo, run `npm install && npm run build` (produces `assets/admin/`), then copy or symlink the folder into `wp-content/plugins/`.

## What it does

| Signal | Endpoint / output |
|---|---|
| Link index | `/llms.txt` |
| Full-text edition | `/llms-full.txt` |
| Markdown delivery | `/<slug>.md` or `Accept: text/markdown` |
| Structured data | JSON-LD `WebSite` + `Person`/`Organization` + `BlogPosting` + `BreadcrumbList` (defers to SEO plugins) |
| XML sitemap | `/agentimus-sitemap.xml` — opt-in fallback, generated **only** when neither WordPress core nor an SEO plugin already provides one (sitemap index + paginated sub-sitemaps) |
| Crawler policy | `robots.txt` content-signal + training-crawler blocklist |
| Discovery layer | `/.well-known/discovery.json` (+ `agent-card.json`, `mcp.json`) |
| Crawl enforcement (opt-in) | hard-block (403) denylisted or spoofed "scanner" user-agents at the generated endpoints — ACME-safe, off by default |

## In the admin

- **Readiness report** — pass/warn/fail checks, each with a plain-English suggestion and a deep link to the fix (including a "sitemap advertised in robots.txt" check).
- **Agent activity log** — a local-only dashboard (no IP logged) of which AI agents and crawlers fetch your endpoints; repeat hits are grouped with a count, newest first.
- **Activity to review** — flags new, unusually high-volume, or spoofed/scanner clients in a nav-bar review queue, each with one-click **Block** (or **Allow**/trust). Pairs with the opt-in *Block scanners & scrapers* enforcement in Settings.
- **Factory reset** — one click restores every setting to its recommended defaults, with a preview of exactly what will change.

## Architecture

- **PHP** (`inc/`, namespace `Agentimus\`, PSR-4 autoloaded) — vanilla, no framework.
  - `Plugin` orchestrates; `Settings` is the single option store; `Cache` handles
    transients; `Endpoints` / `Markdown` / `Schema` produce output; `Readiness`
    runs the checks; `Rest` backs the admin; `Admin` mounts the UI.
- **Admin UI** — Vue 3 (Options API), built with Vite into `assets/admin/`.
  Talks to the REST namespace `agentimus/v1` with the standard WP nonce.

### Extending to any site / content type

The free core covers `post` + `page` and **any public post type you opt in**
(Content types card, or the `agentimus_post_types` filter), so products and
CPTs flow into llms.txt, the full-text edition, markdown and schema automatically.
Deeper coverage (a WooCommerce `Product` mapper, page-builder content) is an
add-on that hooks these seams:

- `agentimus_post_types` — add/remove agent-visible post types
- `agentimus_schema_for_post` — return a full node, e.g. `Product` with offers
- `agentimus_markdown_source` — supply rendered HTML for page-builder content

## For developers — make your plugin discoverable

Agentimus exposes a single aggregated discovery layer at
`/.well-known/discovery.json` (plus `agent-card.json` and `mcp.json`). Any plugin
can register itself with **one action and no dependency** — if Agentimus is not
installed, the hook never fires, so the code is inert:

```php
add_action( 'wpdiscovery_register', function ( $registry ) {
    $registry->register( array(
        'id'           => 'acme-bookings',
        'title'        => 'Acme Bookings',
        'type'         => 'scheduling',                 // controlled vocab + x-vendor-name
        'capabilities' => array( 'scheduling.booking.create' ), // dot-notation INTENT
        'endpoints'    => array(                        // WHERE (concrete paths live here)
            array( 'url' => '/wp-json/acme/v1', 'type' => 'rest', 'auth' => 'apikey' ),
        ),
        'auth'         => array( 'type' => 'apikey', 'docs' => 'https://acme.dev/api' ),
        'agent'        => array( 'name' => 'Acme Agent', 'skills' => array(
            array( 'id' => 'create_booking', 'description' => 'Book an appointment.' ),
        ) ),
    ) );
} );
```

A global facade is also available (guard it, since the call is direct):
```php
if ( class_exists( 'Agentimus_Discovery' ) ) {
    Agentimus_Discovery::register( [...] );
}
```

**Resource fields:** `id` (req, slug), `title` (req), `type` (req — `content`,
`commerce`, `scheduling`, `courses`, `forms`, `crm`, `auth`, `search`, `media`,
`messaging`, `analytics`, `payments`, `directory`, `agent`, or `x-vendor-name`),
`description`, `version`, `capabilities[]`, `endpoints[]` (`{url, type, methods[],
auth, description}`), `schemas[]`, `auth` (`{type, oidc, scopes[], docs}`),
`agent` (`{name, description, skills[{id,description}], endpoint, auth}`),
`abilities[]`, `tools[]` (MCP-shaped), `docs`. `provider` is auto-filled — don't set it.

Capabilities describe **intent**; the concrete `/wp-json/...` paths live only in
`endpoints`/`tools`. Invalid entries are rejected and surfaced (with the reason)
in **Discovery Hub → Validation**.

`$registry->add_well_known( [...] )` serves a `/.well-known/<name>` doc
(callback | redirect | file). See **`examples/integrate-your-plugin.php`** for the
full copy-paste reference, and the [**WP_Discovery Protocol**](https://github.com/heera/wp-discovery-protocol) spec for the standard.

## Hooks & filters

Every hook is optional and falls into one of three tiers. Each is documented below; copy-paste examples with full signatures live in [`examples/all-hooks-reference.php`](examples/all-hooks-reference.php).

### Stable

Public and frozen at WP_Discovery spec 1.0 — safe to build on.

| Hook | Purpose |
| --- | --- |
| `wpdiscovery_register` *(action)* | Register your resources and serve your own `/.well-known` documents. See [`integrate-your-plugin.php`](examples/integrate-your-plugin.php) for the full schema. |
| `agentimus_entity_types` | Add selectable schema.org entity types to Settings → Identity. |
| `agentimus_cache_flushed` *(action)* | Runs after Agentimus regenerates its documents — purge your CDN / page cache. |
| `agentimus_booted` *(action)* | Runs after the plugin boots — a companion or Pro add-on registers its features here. |

```php
// Add selectable schema.org entity types to Settings → Identity.
add_filter( 'agentimus_entity_types', function ( $types ) {
    $types[] = 'Restaurant';
    return $types;
} );
```

### Extension

Supported output-shaping filters; signatures may evolve between releases.

| Hook | Purpose |
| --- | --- |
| `agentimus_envelope` | The whole assembled `discovery.json` — add `x-<vendor>` extension keys. |
| `agentimus_documents` | Add a standard document Agentimus can't auto-detect to the `documents` map. |
| `agentimus_schema_url` | The `$schema` URL of the discovery document; return `''` to omit it. |
| `agentimus_well_known_routed` | Route a flat `/.well-known/<name>` you serve so it resolves on every host. |
| `agentimus_well_known_nested` | Route an exact-match nested `/.well-known/<dir>/<file>`. |
| `agentimus_well_known_specs` | Label a `/.well-known` name with the standard that governs it. |
| `agentimus_signed_surfaces` | Which discovery documents your companion signer signs. |
| `agentimus_mcp` | The advertised MCP descriptor at `/.well-known/mcp.json`. |
| `agentimus_mcp_card_server` | Pin which server the MCP server card describes. |
| `agentimus_agent_skills` | Entries in the Agent Skills index. |
| `agentimus_post_types` | Which post types are agent-visible (each gets an llms.txt section). |
| `agentimus_post_type_source` | Attribute a post type's llms.txt section to your plugin. |
| `agentimus_markdown_source` | Supply rendered HTML for page-builder content. |
| `agentimus_topic_exclude` | Topic/category slugs to omit from the llms.txt Topics list. |
| `agentimus_llms_full_item_max_bytes` | Per-item byte cap for the llms-full.txt edition. |
| `agentimus_llms_full_avg_item_bytes` | Average item size used to estimate llms-full.txt in the admin. |
| `agentimus_yield_surface` | Cede a surface (`llms_txt`, `robots`, …) to your own producer. |
| `agentimus_defer_schema` | Whether to emit the front-end JSON-LD (stand down for an SEO plugin). |
| `agentimus_schema_for_post` | Replace a post's JSON-LD node (e.g. a `Product`). |
| `agentimus_schema_graph` | Last-chance edit of the entire JSON-LD `@graph`. |
| `agentimus_faq_pairs` | Contribute extra FAQPage question/answer pairs. |
| `agentimus_sitemap` | Override the detected sitemap URL. |
| `agentimus_sitemap_max_urls` | Cap the number of URLs in the generated sitemap. |
| `agentimus_rest_discovery` | Master switch for REST namespace auto-discovery. |
| `agentimus_rest_namespaces` | REST namespaces to publish in the discovery document. |
| `agentimus_rest_skip_namespaces` | REST namespaces to exclude from discovery. |
| `agentimus_discoverable_ability` | Include or exclude a single WP ability. |
| `agentimus_serve_security_txt` | Whether Agentimus generates a `security.txt`. |
| `agentimus_security_txt` | Edit the final `security.txt` body. |
| `agentimus_security_txt_expires_days` | The `security.txt` `Expires` window, in days. |
| `agentimus_readiness_checks` | Add or adjust the admin Discovery Hub readiness checks. |
| `agentimus_signing_secret_key` | Supply the Ed25519 signing key from a constant or vault. |

```php
// Add a vendor extension to the discovery document (the x- namespace is yours).
add_filter( 'agentimus_envelope', function ( $envelope, $registry ) {
    $envelope['x-acme'] = array( 'portal' => 'https://acme.example' );
    return $envelope;
}, 10, 2 );
```

### Internal

Advanced site-owner tuning — not a third-party integration surface.

| Hook | Purpose |
| --- | --- |
| `agentimus_deny_request` | The Guard's final say on whether to 403 a request. |
| `agentimus_block_allowlist` | Clients that must never be hard-blocked (search engines + your list). |
| `agentimus_engine_signatures` | Structured signatures that match real crawlers at a token boundary. |
| `agentimus_generic_ua_tokens` | Generic user-agent tokens treated as low-signal. |
| `agentimus_agent_map` | User-agent → friendly label for the activity log. |
| `agentimus_spoof_signatures` | Platform markers that flag a spoofed/legacy-device scanner. |
| `agentimus_known_agents` | Known-agent catalog (user-agent → label). |
| `agentimus_known_scanners` | Scanner user-agents offered as one-click block suggestions. |
| `agentimus_known_trainers` | AI-trainer user-agents offered for robots.txt blocking. |
| `agentimus_ai_referral_sources` | Referrer host → friendly name for "Traffic from AI". |
| `agentimus_activity_skip_self` | Whether to skip recording hits from logged-in admins. |
| `agentimus_activity_retention_days` | How long agent hits are retained. |
| `agentimus_new_agent_seconds` | The "new agent" window for the activity-to-review panel. |
| `agentimus_burst_min_hits` | Minimum hits to flag a burst. |
| `agentimus_heavy_min_hits` | Minimum hits to flag heavy usage. |
| `agentimus_threats_limit` | Maximum rows in the "activity to review" panel. |
| `agentimus_default_settings` | The default settings array. |
| `agentimus_settings` | The live, merged settings array at read time. |
| `agentimus_sanitize_settings` | Validate/coerce companion-added fields on save. |
| `agentimus_settings_reset` *(action)* | Runs when the owner resets settings. |

```php
// The Guard's final say on whether to 403 a request.
add_filter( 'agentimus_deny_request', function ( $deny, $ua ) {
    return $deny;
}, 10, 2 );
```

## Development

```bash
npm install
npm run build      # one-off build into assets/admin/
npm run dev        # rebuild on change
```

`assets/admin/` is git-ignored — it's a build artifact. Ship it in the
distributed `.zip` (the `.org` SVN tag), not the repo.

## Requirements

- WordPress 6.9+ (tested up to 7.0)
- PHP 7.4+.

## License

[GPL-2.0-or-later](LICENSE). The admin app is built from Vue source in `resources/` with Vite — no minified-only code ships, so the build is reproducible.
