<template>
  <BlockFrame type="estimateTotals" :selected="selected" :delete-node="deleteNode">
    <template #actions>
      <button type="button" class="doc-block-button doc-block-button--primary" @click="extension.options.onEditPositions()">
        {{ $t('angebot_document.edit_positions') }}
      </button>
    </template>

    <table class="doc-costs">
      <thead>
        <tr>
          <th>{{ $t('pdf_angebot_position') }}</th>
          <th class="num">{{ $t('pdf_angebot_amount') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td>{{ item.name }}</td>
          <td class="num">{{ formatDocMoney(item.total, currency) }}</td>
        </tr>
        <tr v-if="estimate.discount_val > 0 && estimate.discount_per_item !== 'YES'">
          <td>
            {{ $t('pdf_discount_label') }}
            <template v-if="estimate.discount_type === 'percentage'">({{ estimate.discount }} %)</template>
          </td>
          <td class="num">− {{ formatDocMoney(estimate.discount_val, currency) }}</td>
        </tr>
        <tr class="doc-subtotal">
          <td>{{ $t('pdf_angebot_net') }}</td>
          <td class="num">{{ formatDocMoney(net, currency) }}</td>
        </tr>
        <tr v-for="tax in taxes" :key="tax.key">
          <td>{{ taxLabel(tax) }}</td>
          <td class="num">{{ formatDocMoney(tax.amount, currency) }}</td>
        </tr>
        <tr class="doc-total">
          <td>{{ $t('pdf_angebot_gross') }}</td>
          <td class="num">{{ formatDocMoney(estimateStore.getTotal, currency) }}</td>
        </tr>
      </tbody>
    </table>
  </BlockFrame>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { nodeViewProps } from '@tiptap/vue-3'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import BlockFrame from './BlockFrame.vue'
import { formatDocMoney } from '../format'

defineProps(nodeViewProps)

const { t } = useI18n()
const estimateStore = useEstimateStore()
const estimate = computed(() => estimateStore.newEstimate)
const items = computed(() => estimate.value.items.filter((item) => item.name))
const currency = computed(() => estimate.value.selectedCurrency || null)

const net = computed(() => {
  const discount = estimate.value.discount_per_item !== 'YES' ? estimate.value.discount_val : 0
  const included = estimate.value.tax_included ? estimateStore.getTotalTax : 0
  return estimateStore.getSubTotal - discount - included
})

// The PDF labels use Laravel placeholders (:tax), not vue-i18n ones.
function taxLabel(tax) {
  const key = estimate.value.tax_included ? 'pdf_angebot_tax_included' : 'pdf_angebot_tax_added'
  return t(key).replace(':tax', tax.label)
}

function label(tax) {
  return tax.calculation_type === 'fixed' ? tax.name : `${tax.name} ${Number(tax.percent)} %`
}

const taxes = computed(() => {
  if (estimate.value.tax_per_item === 'YES') {
    const grouped = {}
    for (const item of estimate.value.items) {
      for (const tax of item.taxes || []) {
        if (!tax.tax_type_id) continue
        grouped[tax.tax_type_id] ??= { key: tax.tax_type_id, label: label(tax), amount: 0 }
        grouped[tax.tax_type_id].amount += Number(tax.amount) || 0
      }
    }
    return Object.values(grouped)
  }

  return (estimate.value.taxes || []).map((tax, index) => ({
    key: tax.id ?? index,
    label: label(tax),
    amount: Number(tax.amount) || 0,
  }))
})
</script>
