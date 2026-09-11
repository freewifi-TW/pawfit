<script setup lang="ts">
const { t, tm, rt } = useI18n()

interface Section { title: string, paragraphs?: string[], items?: string[] }

const sections = computed(() => tm('legal.guidelines.sections') as Section[])

useSeoMeta({ title: () => t('legal.guidelines.title'), description: () => t('legal.guidelines.description') })
</script>

<template>
  <article class="prose-page">
    <h1 class="disp">
      {{ t('legal.guidelines.title') }}
    </h1>
    <p class="muted">
      {{ t('legal.guidelines.meta') }}
    </p>

    <template
      v-for="(s, i) in sections"
      :key="i"
    >
      <h2 class="disp">
        {{ rt(s.title) }}
      </h2>
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
