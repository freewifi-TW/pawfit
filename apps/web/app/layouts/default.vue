<template>
  <UApp :toaster="{ position: 'bottom-center' }">
    <AppHeader />
    <main>
      <slot />
    </main>
    <AppFooter />
  </UApp>
</template>
