<template>
  <BlockFrame type="hostingPackages" :selected="selected" :delete-node="deleteNode">
    <template #actions>
      <button type="button" class="doc-block-button doc-block-button--primary" @click="editing = !editing">
        {{ $t('angebot_document.hosting_choose') }}
      </button>
    </template>

    <div v-if="editing" class="doc-block-panel">
      <label v-for="item in choices" :key="item.id" class="doc-block-option">
        <input type="checkbox" :checked="selectedIds.includes(item.id)" @change="toggle(item.id)" />
        {{ item.name }} · {{ formatDocMoney(item.price, currency) }}
      </label>
      <label class="doc-block-option doc-block-option--select">
        {{ $t('angebot_document.hosting_recommended') }}
        <select :value="node.attrs.recommendedId ?? ''" @change="setRecommended($event.target.value)">
          <option value="">{{ $t('angebot_document.hosting_none') }}</option>
          <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
        </select>
      </label>
    </div>

    <table class="doc-hosting">
      <thead>
        <tr>
          <th style="width: 30%">{{ $t('pdf_angebot_package') }}</th>
          <th>{{ $t('pdf_angebot_content') }}</th>
          <th class="num">{{ $t('pdf_angebot_per_month') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="pkg in packages" :key="pkg.id" :class="{ 'doc-recommended': pkg.id === node.attrs.recommendedId }">
          <td>
            <strong>{{ pkg.name }}</strong>
            <span v-if="pkg.id === node.attrs.recommendedId" class="doc-badge">{{ $t('pdf_angebot_recommended') }}</span>
          </td>
          <td>{{ descriptionLines(pkg.description).join(', ') }}</td>
          <td class="num">{{ formatDocMoney(pkg.price, currency) }}</td>
        </tr>
      </tbody>
    </table>
    <p class="doc-note">{{ $t('pdf_angebot_hosting_net') }}</p>
  </BlockFrame>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { nodeViewProps } from '@tiptap/vue-3'
import { useItemStore } from '@/scripts/admin/stores/item'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import BlockFrame from './BlockFrame.vue'
import { descriptionLines, formatDocMoney, isMonthly } from '../format'

const props = defineProps(nodeViewProps)

const itemStore = useItemStore()
const estimateStore = useEstimateStore()
const currency = computed(() => estimateStore.newEstimate.selectedCurrency || null)
const editing = ref(false)

// Hosting and care are billed per month: offer those items, or all when the
// company has none with a monthly unit.
const choices = computed(() => {
  const monthly = itemStore.items.filter((item) => isMonthly(item.unit?.name))
  return monthly.length ? monthly : itemStore.items
})

const selectedIds = computed(() => (props.node.attrs.itemIds || []).map(Number))

const packages = computed(() =>
  selectedIds.value
    .map((id) => itemStore.items.find((item) => item.id === id))
    .filter(Boolean)
)

onMounted(() => {
  if (!itemStore.items.length || itemStore.items.length < itemStore.totalItems) {
    itemStore.fetchItems({ limit: 'all' })
  }
  if (!selectedIds.value.length) {
    editing.value = true
  }
})

function toggle(id) {
  const ids = selectedIds.value.includes(id)
    ? selectedIds.value.filter((existing) => existing !== id)
    : [...selectedIds.value, id]
  props.updateAttributes({
    itemIds: ids,
    recommendedId: ids.includes(props.node.attrs.recommendedId) ? props.node.attrs.recommendedId : null,
  })
}

function setRecommended(value) {
  props.updateAttributes({ recommendedId: value === '' ? null : Number(value) })
}
</script>
