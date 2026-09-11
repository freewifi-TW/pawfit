<script setup lang="ts">
const { t, tm, rt } = useI18n()

interface Term { label: string, body: string }
interface Section { title: string, paragraphs?: string[], items?: string[], terms?: Term[] }

const sections = computed(() => tm('legal.privacy.sections') as Section[])

useSeoMeta({ title: () => t('legal.privacy.title'), description: () => t('legal.privacy.description') })
</script>

<template>
  <article class="prose-page">
    <h1 class="disp">
      {{ t('legal.privacy.title') }}
    </h1>
    <p class="muted">
      {{ t('legal.privacy.meta') }}
    </p>

    <template
      v-for="(s, i) in sections"
      :key="i"
    >
      <h2 class="disp">
        {{ rt(s.title) }}
      </h2>
      <ul v-if="s.terms">
        <li
          v-for="(term, j) in s.terms"
          :key="j"
        >
          <b>{{ rt(term.label) }}</b>{{ rt(term.body) }}
        </li>
      </ul>
      <ul v-if="s.items">
        <li
          v-for="(item, j) in s.items"
          :key="j"
        >
          {{ rt(item) }}
        </li>
      </ul>
      <p
        v-for="(p, j) in s.paragraphs"
        :key="j"
      >
        {{ rt(p) }}
      </p>
    </template>
  </article>
</template>
