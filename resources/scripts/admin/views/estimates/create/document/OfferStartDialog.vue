<template>
  <div class="doc-start-backdrop">
    <div class="doc-start" role="dialog" aria-modal="true" :aria-label="$t('angebot_document.start.title')">
      <h2 class="text-xl font-semibold text-gray-900">{{ $t('angebot_document.start.title') }}</h2>

      <div class="doc-start-grid">
        <section class="doc-start-option">
          <BaseIcon name="DocumentPlusIcon" class="w-6 h-6 text-primary-500" />
          <span class="mt-3 text-sm font-semibold text-gray-900">{{ $t('angebot_document.start.new') }}</span>
          <span class="mt-1 text-xs text-gray-500">{{ $t('angebot_document.start.new_hint') }}</span>

          <div class="doc-start-address" role="radiogroup" :aria-label="$t('angebot_document.address.label')">
            <span class="text-xs text-gray-500">{{ $t('angebot_document.address.label') }}</span>
            <button
              v-for="option in ['sie', 'du']"
              :key="option"
              type="button"
              role="radio"
              :aria-checked="address === option"
              :class="['doc-pill', { 'doc-pill--active': address === option }]"
              @click="address = option"
            >
              {{ $t(`angebot_document.address.${option}`) }}
            </button>
          </div>

          <BaseButton class="mt-4" variant="primary" type="button" @click="$emit('new', address)">
            {{ $t('angebot_document.start.new_button') }}
          </BaseButton>
        </section>

        <section class="doc-start-option">
          <BaseIcon name="DocumentDuplicateIcon" class="w-6 h-6 text-primary-500" />
          <span class="mt-3 text-sm font-semibold text-gray-900">{{ $t('angebot_document.start.copy') }}</span>
          <span class="mt-1 text-xs text-gray-500">{{ $t('angebot_document.start.copy_hint') }}</span>

          <BaseInput
            v-model="search"
            class="mt-3"
            :placeholder="$t('angebot_document.start.search')"
            @update:model-value="searchOffers"
          />
          <div class="doc-start-list">
            <button
              v-for="offer in offers"
              :key="offer.id"
              type="button"
              class="doc-start-offer"
              :disabled="copying !== null"
              @click="copy(offer)"
            >
              <span class="truncate text-sm text-gray-900">{{ offer.customer?.name }}</span>
              <span class="text-xs text-gray-500">
                {{ offer.estimate_number }} · {{ offer.formatted_estimate_date }}
                <template v-if="copying === offer.id"> · {{ $t('angebot_document.start.copying') }}</template>
              </span>
            </button>
            <p v-if="loaded && !offers.length" class="px-2 py-3 text-xs text-gray-500">
              {{ $t('angebot_document.start.no_offers') }}
            </p>
          </div>
        </section>
      </div>

      <button type="button" class="mt-5 text-sm text-gray-500 hover:text-gray-700 hover:underline" @click="$emit('classic')">
        {{ $t('angebot_document.start.classic') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { debounce } from 'lodash'
import http from '@/scripts/http'

const emit = defineEmits(['new', 'copy', 'classic'])

const address = ref('sie')
const search = ref('')
const offers = ref([])
const loaded = ref(false)
const copying = ref(null)

async function loadOffers() {
  const response = await http.get('/api/v1/estimates', {
    params: {
      limit: 8,
      search: search.value || undefined,
      orderByField: 'created_at',
      orderBy: 'desc',
    },
  })
  offers.value = response.data.data
  loaded.value = true
}

const searchOffers = debounce(() => loadOffers().catch(() => {}), 300)

function copy(offer) {
  copying.value = offer.id
  emit('copy', offer.id)
}

onMounted(() => loadOffers().catch(() => {}))
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
  max-width: 720px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  padding: 28px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 20px 50px rgb(0 0 0 / 0.15);
}

.doc-start-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-top: 20px;
}

@media (max-width: 640px) {
  .doc-start-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

.doc-start-option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 18px;
  border: 1px solid rgb(229 231 235);
  border-radius: 10px;
}

.doc-start-address {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 14px;
}

.doc-pill {
  padding: 3px 12px;
  font-size: 13px;
  color: rgb(75 85 99);
  border: 1px solid rgb(229 231 235);
  border-radius: 999px;
}

.doc-pill--active {
  color: var(--color-primary-700);
  background: var(--color-primary-50);
  border-color: var(--color-primary-300);
}

.doc-start-list {
  width: 100%;
  max-height: 220px;
  margin-top: 8px;
  overflow-y: auto;
}

.doc-start-offer {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  padding: 6px 8px;
  text-align: left;
  border-radius: 6px;
}

.doc-start-offer:hover:not(:disabled) {
  background: var(--color-primary-50);
}

.doc-start-option > .base-input,
.doc-start-option > div:not(.doc-start-address) {
  width: 100%;
}
</style>
