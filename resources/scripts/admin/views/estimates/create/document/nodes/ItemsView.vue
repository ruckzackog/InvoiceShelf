<template>
  <BlockFrame type="estimateItems" :selected="selected" :delete-node="deleteNode">
    <template #actions>
      <button type="button" class="doc-block-button doc-block-button--primary" @click="extension.options.onEditPositions()">
        {{ $t('angebot_document.edit_positions') }}
      </button>
    </template>

    <p v-if="!items.length" class="doc-note">{{ $t('angebot_document.no_items') }}</p>

    <div class="doc-packages">
      <div v-for="item in items" :key="item.id" class="doc-package">
        <h3>{{ item.name }}</h3>
        <p v-for="line in workPackage(item.description).intro" :key="line">{{ line }}</p>
        <table>
          <template v-if="workPackage(item.description).rows.length">
            <thead>
              <tr>
                <th style="width: 38%">{{ $t('pdf_angebot_activity') }}</th>
                <th>{{ $t('pdf_angebot_description') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in workPackage(item.description).rows" :key="index">
                <td>{{ row.title }}</td>
                <td>{{ row.text }}</td>
              </tr>
            </tbody>
          </template>
          <tbody>
            <tr class="doc-sum">
              <td>
                {{ $t('pdf_angebot_sum') }}
                <span v-if="Number(item.quantity) !== 1" class="doc-quantity">
                  ({{ Number(item.quantity).toLocaleString('de-AT') }}<template v-if="item.unit_name"> {{ item.unit_name }}</template>
                  × {{ formatDocMoney(item.price, currency) }})
                </span>
              </td>
              <td class="num">{{ formatDocMoney(item.total, currency) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BlockFrame>
</template>

<script setup>
import { computed } from 'vue'
import { nodeViewProps } from '@tiptap/vue-3'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import BlockFrame from './BlockFrame.vue'
import { formatDocMoney, workPackage } from '../format'

defineProps(nodeViewProps)

const estimateStore = useEstimateStore()
const items = computed(() => estimateStore.newEstimate.items.filter((item) => item.name))
const currency = computed(() => estimateStore.newEstimate.selectedCurrency || null)
</script>
