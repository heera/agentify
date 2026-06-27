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

The dev-facing subset below; the plugin fires ~55 in all and every one is optional. They fall into three tiers: **Stable** — the `wpdiscovery_register` registration API plus `agentimus_entity_types` and `agentimus_cache_flushed`, frozen at WP_Discovery spec 1.0; **Extension** — the output-shaping filters listed here, supported but with signatures that may evolve between releases; and **Internal** — advanced Guard/Classifier/Activity/Settings tuning, not a third-party integration surface. The complete, tier-annotated catalogue with every signature is in [`examples/all-hooks-reference.php`](examples/all-hooks-reference.php).

### Stable

Public and frozen at WP_Discovery spec 1.0 — safe to build on.

```php
// Add selectable schema.org entity types to Settings → Identity.
add_filter( 'agentimus_entity_types', function ( $types ) {
    $types[] = 'Restaurant';
    return $types;
} );

// Run after Agentimus regenerates its documents — purge your CDN / page cache.
add_action( 'agentimus_cache_flushed', function () {
    my_cdn_purge( array( '/llms.txt', '/llms-full.txt', '/.well-known/discovery.json' ) );
} );
```

Also stable: the `wpdiscovery_register` registration API (above) and the `agentimus_booted` lifecycle action.

### Extension

Supported output-shaping filters; signatures may evolve between releases.

```php
// Add a vendor extension to the discovery document (the x- namespace is yours).
add_filter( 'agentimus_envelope', function ( $envelope, $registry ) {
    $envelope['x-acme'] = array( 'portal' => 'https://acme.example' );
    return $envelope;
}, 10, 2 );

// Publish your REST namespace, and mark a custom post type agent-visible.
add_filter( 'agentimus_rest_namespaces', function ( $allowed ) {
    $allowed[] = 'acme/v1';
    return $allowed;
} );

add_filter( 'agentimus_post_types', function ( $types, $available ) {
    $types[] = 'acme_product';
    return $types;
}, 10, 2 );
```

Same tier, same shape: `agentimus_documents`, `agentimus_schema_url`, `agentimus_well_known_routed` / `_nested` / `_specs`, `agentimus_signed_surfaces`, `agentimus_mcp`, `agentimus_mcp_card_server`, `agentimus_agent_skills`, `agentimus_post_type_source`, `agentimus_markdown_source`, `agentimus_topic_exclude`, `agentimus_llms_full_item_max_bytes` / `_avg_item_bytes`, `agentimus_yield_surface`, `agentimus_defer_schema`, `agentimus_schema_for_post`, `agentimus_schema_graph`, `agentimus_faq_pairs`, `agentimus_sitemap` / `_max_urls`, `agentimus_rest_discovery` / `_skip_namespaces`, `agentimus_discoverable_ability`, `agentimus_serve_security_txt` / `agentimus_security_txt` / `_expires_days`, `agentimus_readiness_checks`, `agentimus_signing_secret_key`.

### Internal

Advanced Guard / Classifier / Activity / Settings tuning — not a third-party integration surface.

```php
// The Guard's final say on whether to 403 a request.
add_filter( 'agentimus_deny_request', function ( $deny, $ua ) {
    return $deny;
}, 10, 2 );
```

Other internal knobs: `agentimus_block_allowlist`, `agentimus_engine_signatures`, `agentimus_generic_ua_tokens`, `agentimus_agent_map`, `agentimus_spoof_signatures`, `agentimus_known_agents` / `_scanners` / `_trainers`, `agentimus_ai_referral_sources`, `agentimus_activity_skip_self` / `_retention_days`, `agentimus_new_agent_seconds`, `agentimus_burst_min_hits`, `agentimus_heavy_min_hits`, `agentimus_threats_limit`, `agentimus_default_settings` / `agentimus_settings` / `agentimus_sanitize_settings`, `agentimus_settings_reset`.

> Every hook — with its signature, a worked example, and its tier — is in [`examples/all-hooks-reference.php`](examples/all-hooks-reference.php).

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
