=== Agentify ===
Contributors: heera
Tags: ai, llms-txt, mcp, agents, discovery
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control which AIs may train on your content, see which AI bots read your site, and serve clean machine-readable pages. Lightweight, no SEO bloat.

== Description ==

Agentify is a lightweight toolkit for the AI era. **Control** which AI crawlers may train on your content, **see** which AI agents are actually reading your machine-readable endpoints, and **serve** clean, machine-readable versions of your pages — with no heavy SEO suite. A one-screen readiness report shows how machine-readable your site is and what's still missing.

**Control — who may use your content**

* **robots.txt content-signals + AI-training blocklist** — declare your content-usage policy and block named model-training crawlers (GPTBot, CCBot, ClaudeBot, Google-Extended, Bytespider, …) by name, while leaving read/cite bots free.

**Visibility — who is reading you**

* **Agent activity log** — a dashboard of which AI crawlers and agents actually fetch your content and discovery endpoints (GPTBot, Claude, Perplexity, Googlebot, …), recorded first-party with no IP logging.

**Content — clean, machine-readable output**

* **Markdown delivery** — request any page as clean markdown by appending `.md` to its URL, or with an `Accept: text/markdown` header.
* **/llms.txt** & **/llms-full.txt** — an [llmstxt.org](https://llmstxt.org) index of your pages, topics and recent posts, plus a full-text edition an agent can ingest in a single request.
* **JSON-LD** — WebSite + Person/Organization, plus BlogPosting and BreadcrumbList on posts. Automatically **defers to Yoast, Rank Math, SEOPress, AIOSEO and The SEO Framework** so you never ship duplicate schema.

**Machine discovery & MCP**

Agentify also publishes a single, normalized discovery layer, built to the conventions the agent ecosystem is converging on (the `.well-known` standard, A2A agent cards, MCP-shaped tools). It puts a site's identity, capabilities and APIs in one predictable place, so a client built to read it has nothing to reverse-engineer:

* **/.well-known/discovery.json** — a registry of the site's identity, capabilities, APIs and agent cards. Plugins declare themselves through one hook (`agentify_discovery_register`), so everything an agent would need is aggregated in one place.
* **/.well-known/agent-card.json** and **/.well-known/mcp.json** — an A2A agent card and an MCP manifest, generated automatically.
* **WordPress Abilities API → MCP tools** — registered abilities are projected into MCP-shaped tools, and a running MCP server (if present) is detected and linked.
* **Zero-config auto-discovery** — reads your registered REST API namespaces, public post types and the WordPress Abilities API, so a site is discoverable even when no plugin declares itself. A **Discovery Hub** admin screen shows the registry, providers, tools and validation.

**What's read today vs. what it readies you for**

Honest framing: the content signals above (JSON-LD, sitemap, robots, llms.txt, markdown) are read by search engines and AI tools **today**. The discovery layer is **forward-looking and standards-aligned** — it makes your site ready for AI agents as they adopt these conventions, rather than claiming every agent already reads it. The discovery format is an open protocol with a reference client, not a private format.

**Why it's different**

Other plugins do one slice — an llms.txt file, or an AI-bot blocker, or structured data. Agentify combines content control, agent-traffic visibility, clean machine-readable output **and** a forward-looking discovery layer in one coherent, lightweight package — and tells you what's still missing.

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
* MCP & tools: projects the WordPress Abilities API into MCP-shaped tools, /.well-known/mcp.json and agent-card.json. Zero-config auto-discovery of REST namespaces and public post types.
* Admin Discovery Hub for inspecting the registry, providers, tools and validation.
