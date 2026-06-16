<script>
export default {
  name: 'DiscoveryHub',
  props: {
    data: { type: Object, default: () => ({}) },
    refreshing: { type: Boolean, default: false },
  },
  emits: ['refresh'],
  computed: {
    endpoints() {
      return this.data.endpoints || {};
    },
    counts() {
      return this.data.counts || { resources: 0, capabilities: 0, apis: 0, errors: 0 };
    },
    resources() {
      return this.data.resources || [];
    },
    adapters() {
      return this.data.adapters || [];
    },
    capabilities() {
      return this.data.capabilities || [];
    },
    tools() {
      return this.data.tools || [];
    },
    mcp() {
      return this.data.mcp || { available: false, source: '', transport: '', tools: 0 };
    },
    wellKnown() {
      return this.data.wellKnown || [];
    },
    notices() {
      return this.data.notices || [];
    },
    discoveryUrl() {
      return this.endpoints.discovery || '';
    },
    discoveryPath() {
      try {
        return new URL(this.discoveryUrl).pathname;
      } catch (e) {
        return '/.well-known/discovery.json';
      }
    },
  },
  methods: {
    sourceLabel(source) {
      return { file: 'ON DISK', managed: 'MANAGED', generated: 'GENERATED' }[source] || source.toUpperCase();
    },
  },
};
</script>

<template>
  <div class="ar-wd">
    <!-- Canonical endpoint -->
    <section class="ar-card ar-wd-endpoint">
      <h2 class="ar-card__title">Discovery endpoint</h2>

      <div class="ar-wd-endpoint-row">
        <div class="ar-wd-canonical">
          <span class="ar-wd-canonical__method">GET</span>
          <span class="ar-wd-canonical__path">{{ discoveryPath }}</span>
          <a class="ar-wd-canonical__ext" :href="discoveryUrl" target="_blank" rel="noopener" aria-label="Open discovery.json in a new tab">↗</a>
        </div>
        <button type="button" class="ar-btn" :disabled="refreshing" @click="$emit('refresh')">
          {{ refreshing ? 'Scanning…' : 'Re-scan' }}
        </button>
      </div>

      <ul class="ar-links ar-wd-altlinks">
        <li><a :href="endpoints.agentCard" target="_blank" rel="noopener">agent-card.json</a></li>
        <li><a :href="endpoints.agentJson" target="_blank" rel="noopener">agent.json (alias)</a></li>
        <li><a :href="endpoints.rest" target="_blank" rel="noopener">REST: /agentify/v1/discovery</a></li>
      </ul>

      <div class="ar-wd-stats">
        <div class="ar-wd-stat">
          <strong>{{ counts.resources }}</strong>
          <span>providers</span>
        </div>
        <div class="ar-wd-stat">
          <strong>{{ counts.capabilities }}</strong>
          <span>capabilities</span>
        </div>
        <div class="ar-wd-stat">
          <strong>{{ counts.tools }}</strong>
          <span>tools</span>
        </div>
        <div class="ar-wd-stat">
          <strong>{{ counts.apis }}</strong>
          <span>APIs</span>
        </div>
        <div class="ar-wd-stat" :class="{ 'is-bad': counts.errors > 0 }">
          <strong>{{ counts.errors }}</strong>
          <span>errors</span>
        </div>
      </div>
    </section>

    <!-- Registered providers -->
    <section class="ar-card">
      <h2 class="ar-card__title">Registered providers</h2>
      <p class="ar-card__lead">
        Resources discovered on this site — auto-detected by Agentify, or declared by a plugin via the <code>wpdiscovery_register</code> hook.
      </p>

      <p v-if="!resources.length" class="ar-wd-empty">
        No plugins have registered yet. Install a WP_Discovery-aware plugin, or one of the built-in
        adapters below will populate this automatically.
      </p>

      <ul v-else class="ar-wd-list">
        <li v-for="r in resources" :key="r.id" class="ar-wd-prov">
          <div class="ar-wd-prov__bar" aria-hidden="true"></div>
          <div class="ar-wd-prov__body">
            <div class="ar-wd-prov__head">
              <strong>{{ r.title }}</strong>
              <span class="ar-wd-type">{{ r.type }}</span>
              <span v-if="r.hasAgent" class="ar-wd-type ar-wd-type--agent">agent</span>
              <span v-if="r.version" class="ar-wd-ver">v{{ r.version }}</span>
            </div>
            <p v-if="r.description" class="ar-wd-prov__desc">{{ r.description }}</p>
            <p class="ar-wd-prov__provider">
              <span v-if="r.auto">Auto-discovered by Agentify</span>
              <span v-else>Declared by <code>{{ r.provider }}</code></span>
            </p>

            <div v-if="r.capabilities.length" class="ar-wd-caps">
              <span v-for="c in r.capabilities" :key="c" class="ar-wd-cap">{{ c }}</span>
            </div>

            <ul v-if="r.endpoints.length" class="ar-wd-eps">
              <li v-for="(e, i) in r.endpoints" :key="i">
                <span class="ar-wd-ep__type">{{ e.type }}</span>
                <code>{{ e.url }}</code>
                <span class="ar-wd-auth" :class="`is-${e.auth === 'none' ? 'open' : 'locked'}`">
                  {{ e.auth === 'none' ? 'public' : e.auth }}
                </span>
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </section>

    <!-- Built-in adapters -->
    <section class="ar-card ar-card--muted">
      <h2 class="ar-card__title">Built-in adapters</h2>
      <p class="ar-card__lead">
        First-party providers that register through the same public hook — active automatically when
        their plugin is present.
      </p>
      <ul class="ar-checks">
        <li v-for="a in adapters" :key="a.id" class="ar-check" :class="a.available ? 'is-pass' : 'is-warn'">
          <span class="ar-check__rule" aria-hidden="true"></span>
          <div class="ar-check__text">
            <strong>{{ a.title }}</strong>
            <small>{{ a.available ? 'Detected — contributing to the registry.' : 'Not installed — will activate when present.' }}</small>
          </div>
          <span class="ar-check__tag" :class="a.available ? 'is-pass' : 'is-warn'">
            {{ a.available ? 'ACTIVE' : 'INACTIVE' }}
          </span>
        </li>
      </ul>
    </section>

    <!-- MCP & tools -->
    <section class="ar-card">
      <h2 class="ar-card__title">MCP &amp; tools</h2>
      <p class="ar-card__lead">
        Executable units from the WordPress Abilities API, projected into MCP tool shape and
        published at <code>/.well-known/mcp.json</code>. This plugin advertises tools — it doesn't run
        an MCP server.
      </p>

      <div class="ar-wd-mcp">
        <div class="ar-wd-mcp__cell">
          <span>MCP server</span>
          <strong :class="mcp.available ? 'is-on' : 'is-off'">{{ mcp.available ? 'detected' : 'none' }}</strong>
        </div>
        <div class="ar-wd-mcp__cell">
          <span>source</span><strong>{{ mcp.source || '—' }}</strong>
        </div>
        <div class="ar-wd-mcp__cell">
          <span>transport</span><strong>{{ mcp.transport || '—' }}</strong>
        </div>
        <div class="ar-wd-mcp__cell">
          <span>tools</span><strong>{{ mcp.tools }}</strong>
        </div>
      </div>

      <div v-if="mcp.available && mcp.endpoint" class="ar-wd-canonical ar-wd-mcp-endpoint">
        <span class="ar-wd-canonical__method">MCP</span>
        <span class="ar-wd-canonical__path">{{ mcp.endpoint }}</span>
        <a class="ar-wd-canonical__ext" :href="mcp.endpoint" target="_blank" rel="noopener" aria-label="Open mcp.json in a new tab">↗</a>
      </div>

      <p v-if="!mcp.available && mcp.source === 'abilities'" class="ar-wd-note">
        Tools are registered via the Abilities API, but no MCP server is installed. Add an MCP adapter
        to make them callable; agents can still read the signatures here.
      </p>

      <ul v-if="tools.length" class="ar-wd-tools">
        <li v-for="t in tools" :key="t.name" class="ar-wd-tool">
          <div class="ar-wd-tool__id">
            <code>{{ t.name }}</code>
            <span v-if="t.title" class="ar-wd-tool__title">{{ t.title }}</span>
          </div>
          <div class="ar-wd-tool__meta">
            <span v-if="t.annotations && t.annotations.readOnlyHint" class="ar-wd-badge">read-only</span>
            <span v-if="t.inputSchema && Object.keys(t.inputSchema).length" class="ar-wd-badge ar-wd-badge--schema">schema</span>
            <span class="ar-wd-auth" :class="t.auth === 'none' ? 'is-open' : 'is-locked'">
              {{ t.auth === 'none' ? 'public' : t.auth }}
            </span>
          </div>
        </li>
      </ul>
      <p v-else class="ar-wd-empty">
        No tools registered. Install the Abilities API (heading for core) or an MCP-aware plugin.
      </p>
    </section>

    <!-- Well-known + validation, side by side -->
    <div class="ar-wd-cols">
      <section class="ar-card">
        <h2 class="ar-card__title">Well-known documents</h2>
        <ul class="ar-wd-wk">
          <li v-for="w in wellKnown" :key="w.name">
            <a :href="w.url" target="_blank" rel="noopener"><code>/.well-known/{{ w.name }}</code></a>
            <span class="ar-wd-src" :class="`is-${w.source}`">{{ sourceLabel(w.source) }}</span>
          </li>
        </ul>
      </section>

      <section class="ar-card" :class="{ 'ar-wd-valid': !notices.length }">
        <h2 class="ar-card__title">Validation</h2>
        <div v-if="!notices.length" class="ar-wd-allclear">
          <span class="ar-wd-allclear__badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7" /></svg>
          </span>
          <p class="ar-wd-allclear__title">All registrations valid</p>
          <p class="ar-wd-allclear__sub">No schema or conflict issues detected.</p>
        </div>
        <ul v-else class="ar-wd-notices">
          <li v-for="(n, i) in notices" :key="i" class="ar-wd-notice" :class="`is-${n.level}`">
            <span class="ar-wd-notice__tag">{{ n.level.toUpperCase() }}</span>
            <span>{{ n.message }}</span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
