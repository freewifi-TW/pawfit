<script setup lang="ts">
const { t, tm, rt } = useI18n()

interface LinkParagraph { before: string, text: string, after: string, to: string }
interface Section { title: string, paragraphs?: string[], items?: string[], link?: LinkParagraph }

const sections = computed(() => tm('legal.terms.sections') as Section[])

useSeoMeta({ title: () => t('legal.terms.title'), description: () => t('legal.terms.description') })
</script>

<template>
  <article class="prose-page">
    <h1 class="disp">
      {{ t('legal.terms.title') }}
    </h1>
    <p class="muted">
      {{ t('legal.terms.meta') }}
    </p>

    <template
      v-for="(s, i) in sections"
      :key="i"
    >
      <h2 class="disp">
        {{ rt(s.title) }}
      </h2>
      <p
        v-for="(p, j) in s.paragraphs"
        :key="j"
      >
        {{ rt(p) }}
      </p>
      <ul v-if="s.items">
        <li
          v-for="(item, j) in s.items"
          :key="j"
        >
          {{ rt(item) }}
        </li>
      </ul>
      <p v-if="s.link">
        {{ rt(s.link.before) }}<NuxtLink
          class="link"
          :to="rt(s.link.to)"
        >{{ rt(s.link.text) }}</NuxtLink>{{ rt(s.link.after) }}
      </p>
    </template>
  </article>
</template>
