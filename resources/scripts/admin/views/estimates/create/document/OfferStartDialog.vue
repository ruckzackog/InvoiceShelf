<template>
  <div class="doc-start-backdrop">
    <div class="doc-start" role="dialog" aria-modal="true" :aria-label="$t('angebot_document.start.title')">
      <h2 class="text-xl font-semibold text-gray-900">{{ $t('angebot_document.start.title') }}</h2>
      <p class="mt-1 text-sm text-gray-500">{{ $t('angebot_document.start.subtitle') }}</p>

      <div class="doc-start-grid">
        <button
          v-for="(starter, key) in STARTERS"
          :key="key"
          type="button"
          class="doc-start-option"
          @click="$emit('choose', key)"
        >
          <BaseIcon :name="starter.icon" class="w-6 h-6 text-primary-500" />
          <span class="mt-3 text-sm font-semibold text-gray-900">{{ $t(`angebot_document.start.${key}`) }}</span>
          <span class="mt-1 text-xs text-gray-500">
            {{ starter.packages.length
              ? $t('angebot_document.start.packages', { count: starter.packages.length })
              : $t('angebot_document.start.empty_hint') }}
          </span>
        </button>
      </div>

      <button type="button" class="mt-5 text-sm text-gray-500 hover:text-gray-700 hover:underline" @click="$emit('classic')">
        {{ $t('angebot_document.start.classic') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { STARTERS } from './starters'

defineEmits(['choose', 'classic'])
</script>

<style>
.doc-start-backdrop {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgb(55 65 81 / 0.35);
}

.doc-start {
  width: 100%;
  max-width: 640px;
  padding: 28px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 20px 50px rgb(0 0 0 / 0.15);
}

.doc-start-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-top: 20px;
}

@media (max-width: 640px) {
  .doc-start-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.doc-start-option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 16px;
  text-align: left;
  border: 1px solid rgb(229 231 235);
  border-radius: 10px;
  transition: border-color 0.15s, background 0.15s;
}

.doc-start-option:hover,
.doc-start-option:focus-visible {
  border-color: var(--color-primary-400);
  background: var(--color-primary-50);
  outline: none;
}
</style>
