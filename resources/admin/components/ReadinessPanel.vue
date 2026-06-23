<script>
import { groupChecks } from '../tiers.js';
import { runAll } from '../livecheck.js';

export default {
  name: 'ReadinessPanel',
  props: {
    checks: { type: Array, default: () => [] },
    refreshing: { type: Boolean, default: false },
    liveConfig: { type: Object, default: () => ({}) },
  },
  emits: ['refresh', 'navigate'],
  data() {
    return { live: null, liveRunning: false };
  },
  computed: {
    // The same checks, grouped under the Findable → Readable → Trusted rungs.
    groups() {
      return groupChecks(this.checks);
    },
    livePass() {
      return this.live ? this.live.filter((r) => r.ok).length : 0;
    },
  },
  methods: {
    tagLabel(status) {
      return { pass: 'PASS', warn: 'WARN', fail: 'FAIL' }[status] || status.toUpperCase();
    },
    // Fetch the real endpoints from this browser and grade what an agent receives.
    // The server makes no request — this runs here, same-origin, on click only.
    async verifyLive() {
      if (this.liveRunning) return;
      this.liveRunning = true;
      try {
        this.live = await runAll(this.liveConfig);
      } finally {
        this.liveRunning = false;
      }
    },
  },
};
</script>

<template>
  <section class="ar-card">
    <div class="ar-card__head">
      <h2 class="ar-card__title">Readiness report</h2>
      <div class="ar-card__actions">
        <button type="button" class="ar-btn" :disabled="liveRunning" @click="verifyLive">
          {{ liveRunning ? 'Checking…' : 'Verify live' }}
        </button>
        <button type="button" class="ar-btn" :disabled="refreshing" @click="$emit('refresh')">
          {{ refreshing ? 'Running…' : 'Re-run' }}
        </button>
      </div>
    </div>

    <div v-if="live || liveRunning" class="ar-live">
      <div class="ar-live__head">
        <h3 class="ar-live__title">What agents actually receive</h3>
        <div class="ar-live__meta">
          <span v-if="live" class="ar-live__tally" :class="{ 'is-bad': livePass < live.length }">
            {{ livePass }}/{{ live.length }} OK
          </span>
          <button
            v-if="live && !liveRunning"
            type="button"
            class="ar-live__close"
            aria-label="Dismiss live results"
            @click="live = null"
          >&times;</button>
        </div>
      </div>
      <p class="ar-live__lead">
        Fetched from your browser through the public URL — so this reflects what an agent gets
        (including anything a CDN serves), not just your settings. The server makes no request.
      </p>
      <ul v-if="live" class="ar-live__list">
        <li v-for="r in live" :key="r.key" class="ar-live__row" :class="{ 'is-bad': !r.ok }">
          <span class="ar-live__dot" aria-hidden="true"></span>
          <span class="ar-live__label">{{ r.label }}</span>
          <span class="ar-live__detail">{{ r.detail }}</span>
        </li>
      </ul>
      <p v-else class="ar-live__running">Fetching your endpoints…</p>
    </div>

    <div
      v-for="g in groups"
      :key="g.key"
      class="ar-checkgroup"
      :class="`is-${g.status}`"
    >
      <div class="ar-checkgroup__head">
        <span class="ar-checkgroup__rung" aria-hidden="true"></span>
        <div class="ar-checkgroup__text">
          <h3 class="ar-checkgroup__name">{{ g.label }}</h3>
          <p v-if="g.blurb" class="ar-checkgroup__blurb">{{ g.blurb }}</p>
        </div>
        <span class="ar-checkgroup__count">{{ g.pass }}/{{ g.total }}</span>
      </div>

      <ul class="ar-checks">
        <li v-for="c in g.items" :id="`ar-check-${c.id}`" :key="c.id" class="ar-check" :class="`is-${c.status}`">
          <span class="ar-check__rule" aria-hidden="true"></span>
          <div class="ar-check__text">
            <strong>{{ c.label }}</strong>
            <small>{{ c.detail }}</small>
            <p v-if="c.fix" class="ar-check__fix">{{ c.fix }}</p>
            <a
              v-if="c.action && c.action.href"
              class="ar-check__action"
              :href="c.action.href"
              target="_blank"
              rel="noopener"
            >{{ c.action.label }} ↗</a>
            <button
              v-else-if="c.action"
              type="button"
              class="ar-check__action"
              @click="$emit('navigate', { tab: c.action.tab, anchor: c.action.anchor })"
            >{{ c.action.label }} →</button>
          </div>
          <span class="ar-check__tag" :class="`is-${c.status}`">{{ tagLabel(c.status) }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
