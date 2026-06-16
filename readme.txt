=== Agentify ===
Contributors: heera
Tags: ai, llms-txt, mcp, agents, discovery
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your site legible to AI agents — llms.txt, markdown, JSON-LD, a /.well-known/discovery.json registry and MCP tools. Lightweight, no SEO bloat.

== Description ==

Agentify turns your site into a first-class citizen for AI agents, retrieval systems and modern crawlers — without the weight of an SEO suite. It ships five focused signals and a one-screen readiness report so you can see exactly how machine-readable your site is.

**What it adds**

* **/llms.txt** — an [llmstxt.org](https://llmstxt.org) index of your pages, topics and recent posts, with an About/Expertise identity block up top.
* **/llms-full.txt** — the full-text edition: every page and recent post concatenated into one document an agent can ingest in a single request.
* **Markdown delivery** — request any page as clean markdown by appending `.md` to its URL, or with an `Accept: text/markdown` header.
* **JSON-LD** — WebSite + Person/Organization entity, plus BlogPosting and BreadcrumbList on posts. Automatically **defers to Yoast, Rank Math, SEOPress, AIOSEO and The SEO Framework** so you never ship duplicate schema.
* **robots.txt** — a content-signal usage declaration and a model-training crawler blocklist, while leaving read/cite bots free.

**Machine discovery & MCP**

Agentify also exposes a single, aggregated discovery layer so AI agents can understand the *whole* site from one request:

* **/.well-known/discovery.json** — a registry of the site's identity, capabilities, APIs and agent cards. Plugins declare themselves through one hook (`agentify_discovery_register`), so an agent never has to reverse-engineer your stack.
* **/.well-known/agent-card.json** and **/.well-known/mcp.json** — an A2A agent card and an MCP manifest, generated automatically.
* **WordPress Abilities API → MCP tools** — registered abilities are projected into MCP-shaped tools, and a running MCP server (if present) is detected and linked.
* **Built-in adapters** for WooCommerce and Fluent Cart, plus a **Discovery Hub** admin screen to inspect the registry, providers, tools and validation.

**Why it's different**

Most "llms.txt" plugins stop at the index file. Agentify pairs that with markdown content-negotiation, full structured data, robots content-signals **and a machine-discovery registry** as one coherent, lightweight package — and tells you what's still missing.

== Installation ==

1. Upload the `agentify` folder to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate the plugin.
3. Open **Agentify** in the admin menu, fill in your Identity (name, profile sentence, expertise, sameAs links) and review the readiness report.

== Frequently Asked Questions ==

= Does this conflict with my SEO plugin? =

No. JSON-LD output automatically stands down when Yoast, Rank Math, SEOPress, AIOSEO or The SEO Framework is active, so structured data is never duplicated. The other endpoints (llms.txt, markdown) don't overlap with SEO plugins.

= My robots.txt rules aren't showing. =

If a static `robots.txt` file exists at your site root, or your CDN serves its own, it overrides WordPress's virtual robots.txt. The readiness report flags this. Remove the static file to let Agentify manage the rules.

= Will it slow my site down? =

No. The text endpoints are cached and CDN-friendly; there is no front-end JavaScript or CSS. The admin app loads only on the plugin's own screen.

= How do I make my plugin appear in the discovery registry? =

Add a single action — no dependency, no library. If Agentify isn't installed the hook simply never fires:

`add_action( 'agentify_discovery_register', function ( $registry ) {`
`    $registry->register( array( 'id' => 'acme', 'title' => 'Acme', 'type' => 'commerce' ) );`
`} );`

See `examples/integrate-your-plugin.php` and the README for the full resource schema (capabilities, endpoints, auth, agent cards, MCP tools).

== Screenshots ==

1. The Agentify settings screen with the readiness score.
2. The readiness report.
3. The Discovery Hub — providers, capabilities, tools and the well-known endpoints.

== Source & build ==

There is no minified-only code. The admin interface is built from Vue 3 source in `resources/` with Vite; the source and `vite.config.js` ship in this package and also live in the public repository at https://github.com/heera/agentify . Run `npm install && npm run build` to regenerate `assets/admin/` from source.

== Changelog ==

= 1.0.0 =
* /llms.txt, /llms-full.txt, markdown delivery, JSON-LD, robots content-signals, and a readiness report.
* Machine discovery layer: /.well-known/discovery.json with a public registration hook (agentify_discovery_register) for plugins to declare capabilities, APIs and agent cards.
* MCP & tools: projects the WordPress Abilities API into MCP-shaped tools, /.well-known/mcp.json and agent-card.json. Built-in WooCommerce and Fluent Cart adapters.
* Admin Discovery Hub for inspecting the registry, providers, tools and validation.
