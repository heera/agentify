<script>
import { groupChecks } from '../tiers.js';

export default {
  name: 'ReadinessPanel',
  props: {
    checks: { type: Array, default: () => [] },
    refreshing: { type: Boolean, default: false },
  },
  emits: ['refresh', 'navigate'],
  computed: {
    // The same checks, grouped under the Findable → Readable → Trusted rungs.
    groups() {
      return groupChecks(this.checks);
    },
  },
  methods: {
    tagLabel(status) {
      return { pass: 'PASS', warn: 'WARN', fail: 'FAIL' }[status] || status.toUpperCase();
    },
  },
};
</script>

<template>
  <section class="ar-card">
    <div class="ar-card__head">
      <h2 class="ar-card__title">Readiness report</h2>
      <button type="button" class="ar-btn" :disabled="refreshing" @click="$emit('refresh')">
        {{ refreshing ? 'Running…' : 'Re-run' }}
      </button>
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
