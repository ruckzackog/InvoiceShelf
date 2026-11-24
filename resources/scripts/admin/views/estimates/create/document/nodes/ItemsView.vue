<template>
  <BlockFrame type="estimateItems" :selected="selected" :delete-node="deleteNode">
    <div class="doc-packages">
      <div v-for="({ item, intro, rows }, index) in packages" :key="item.id" class="doc-package doc-package--editable" :data-package="index">
        <button
          v-if="items.length > 1"
          type="button"
          class="doc-package-remove"
          :title="$t('angebot_document.remove_package')"
          @click="removePackage(index)"
        >
          <TrashIcon />
        </button>

        <p v-if="isMonthly(item.unit_name)" class="doc-warning" contenteditable="false">
          {{ $t('angebot_document.monthly_warning') }}
          <button type="button" class="doc-link-button" @click="removePackage(index)">
            {{ $t('angebot_document.remove_package') }}
          </button>
        </p>

        <input
          class="doc-inline-input doc-package-title"
          :value="item.name"
          :placeholder="$t('angebot_document.package_name')"
          @input="update(index, { name: $event.target.value })"
        />

        <AutoTextarea
          class="doc-inline-input doc-package-intro"
          :model-value="intro.join('\n')"
          :placeholder="$t('angebot_document.package_intro')"
          @update:model-value="setIntro(index, $event)"
        />

        <table>
          <thead>
            <tr>
              <th style="width: 38%">{{ hasText(rows) ? $t('pdf_angebot_activity') : $t('pdf_angebot_service') }}</th>
              <th>{{ hasText(rows) ? $t('pdf_angebot_description') : '' }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, rowIndex) in rows" :key="rowIndex" class="doc-activity">
              <td>
                <input
                  class="doc-inline-input"
                  :value="row.title"
                  :placeholder="$t('pdf_angebot_activity')"
                  @input="setRow(index, rowIndex, { title: $event.target.value })"
                  @keydown.enter.prevent="addRow(index, rowIndex + 1)"
                />
              </td>
              <td class="doc-activity-text">
                <AutoTextarea
                  class="doc-inline-input"
                  :model-value="row.text"
                  :placeholder="$t('pdf_angebot_description')"
                  @update:model-value="setRow(index, rowIndex, { text: $event })"
                />
                <button type="button" class="doc-row-remove" :title="$t('angebot_document.remove_activity')" @click="removeRow(index, rowIndex)">
                  <XMarkIcon />
                </button>
              </td>
            </tr>
            <tr class="doc-add-row">
              <td colspan="2">
                <button type="button" class="doc-link-button" @click="addRow(index)">
                  + {{ hasText(rows) ? $t('angebot_document.add_activity') : $t('pdf_angebot_service') }}
                </button>
              </td>
            </tr>
          </tbody>
          <tbody>
            <tr class="doc-sum">
              <td>
                {{ $t('pdf_angebot_sum') }}
                <span class="doc-quantity doc-price-inputs">
                  <input
                    class="doc-inline-input doc-number"
                    :value="formatQuantity(item.quantity)"
                    :title="$t('angebot_document.quantity')"
                    inputmode="decimal"
                    @change="update(index, { quantity: parseQuantity($event.target.value) })"
                  />
                  <template v-if="item.unit_name">{{ item.unit_name }}</template>
                  ×
                  <input
                    class="doc-inline-input doc-number doc-price"
                    :value="formatMoneyInput(item.price)"
                    :title="$t('angebot_document.price')"
                    inputmode="decimal"
                    @change="update(index, { price: parseMoneyInput($event.target.value) }); $event.target.value = formatMoneyInput(items[index].price)"
                  />
                  {{ currencySymbol }}
                </span>
              </td>
              <td class="num">{{ formatDocMoney(item.total, currency) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="doc-add-package">
      <button type="button" class="doc-link-button" @click="addPackage()">
        + {{ $t('angebot_document.add_package') }}
      </button>
      <select v-if="oneTimeArticles.length" class="doc-from-items" value="" @change="addFromArticle($event)">
        <option value="">{{ $t('angebot_document.add_from_items') }}</option>
        <option v-for="article in oneTimeArticles" :key="article.id" :value="article.id">
          {{ article.name }} · {{ formatDocMoney(article.price, currency) }}
        </option>
      </select>
      <button type="button" class="doc-link-button doc-link-button--muted" @click="extension.options.onEditPositions()">
        {{ $t('angebot_document.more_in_form') }}
      </button>
    </div>
  </BlockFrame>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { nodeViewProps } from '@tiptap/vue-3'
import { TrashIcon, XMarkIcon } from '@heroicons/vue/20/solid'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useItemStore } from '@/scripts/admin/stores/item'
import BlockFrame from './BlockFrame.vue'
import AutoTextarea from './AutoTextarea.vue'
import {
  isArchived,
  isMonthly,
  formatDocMoney,
  formatMoneyInput,
  packageDescription,
  parseMoneyInput,
  workPackage,
} from '../format'

defineProps(nodeViewProps)

const estimateStore = useEstimateStore()
const itemStore = useItemStore()

// All rows of the estimate, also empty new ones: they are edited here.
const items = computed(() => estimateStore.newEstimate.items)
const currency = computed(() => estimateStore.newEstimate.selectedCurrency || null)
const currencySymbol = computed(() => currency.value?.symbol || '€')

// Monthly items (hosting, care, licences) go in the hosting block.
const oneTimeArticles = computed(() =>
  itemStore.items.filter((article) => !isMonthly(article.unit?.name) && !isArchived(article))
)

function hasText(rows) {
  return rows.some((row) => row.text.trim())
}

onMounted(() => {
  if (!itemStore.items.length || itemStore.items.length < itemStore.totalItems) {
    itemStore.fetchItems({ limit: 'all' })
  }
})

// Activity rows are edited in a draft per package: an empty row the user just
// added must not vanish, but the description only keeps filled rows. The
// draft is dropped as soon as the description changes elsewhere (Formular).
const drafts = new Map()
const version = ref(0)

function draftFor(item) {
  const draft = drafts.get(item.id)
  if (draft && packageDescription(draft) === (item.description || '')) {
    return draft
  }
  const fresh = workPackage(item.description)
  drafts.set(item.id, fresh)
  return fresh
}

const packages = computed(() => {
  version.value
  return items.value.map((item) => ({ item, ...draftFor(item) }))
})

/**
 * Writes a change the same way the form row does (CreateItemRow
 * syncItemToStore): price, quantity and discount give sub_total and total.
 * The form row stays mounted in the background and recalculates the item
 * taxes from the new total.
 */
function update(index, patch) {
  const item = { ...items.value[index], ...patch }
  const subTotal = Math.round((Number(item.price) || 0) * (Number(item.quantity) || 0))
  const discountVal =
    item.discount_type === 'percentage'
      ? Math.round((Math.abs(subTotal) * (Number(item.discount) || 0)) / 100)
      : Math.min(Number(item.discount_val) || 0, Math.abs(subTotal))

  estimateStore.updateItem({
    ...item,
    index,
    sub_total: subTotal,
    discount_val: discountVal,
    total: subTotal - discountVal,
  })
}

function editDraft(index, change) {
  const item = items.value[index]
  const draft = draftFor(item)
  change(draft)
  drafts.set(item.id, draft)
  version.value++
  update(index, { description: packageDescription(draft) })
}

function setIntro(index, text) {
  editDraft(index, (draft) => {
    draft.intro = text.split('\n')
  })
}

function setRow(index, rowIndex, patch) {
  editDraft(index, (draft) => {
    draft.rows[rowIndex] = { ...draft.rows[rowIndex], ...patch }
  })
}

function addRow(index, at = null) {
  let position = at
  editDraft(index, (draft) => {
    position = at ?? draft.rows.length
    draft.rows.splice(position, 0, { title: '', text: '' })
  })
  nextTick(() => {
    document
      .querySelectorAll(`[data-package="${index}"] .doc-activity`)
      [position]?.querySelector('input')
      ?.focus()
  })
}

function removeRow(index, rowIndex) {
  editDraft(index, (draft) => {
    draft.rows.splice(rowIndex, 1)
  })
}

function addPackage(patch = {}) {
  const empty = items.value.length === 1 && !items.value[0].name && !items.value[0].price
  if (!empty) {
    estimateStore.addItem()
  }
  update(items.value.length - 1, { quantity: 1, ...patch })
}

function addFromArticle(event) {
  const article = itemStore.items.find((item) => String(item.id) === event.target.value)
  event.target.value = ''
  if (!article) return

  addPackage({
    name: article.name,
    description: article.description,
    price: article.price,
    item_id: article.id,
    unit_name: article.unit?.name ?? null,
  })
}

function removePackage(index) {
  estimateStore.removeItem(index)
}

function formatQuantity(quantity) {
  return Number(quantity || 0).toLocaleString('de-DE', { maximumFractionDigits: 2 })
}

function parseQuantity(value) {
  const amount = Number.parseFloat(String(value).replace(/\./g, '').replace(',', '.'))
  return Number.isFinite(amount) && amount > 0 ? amount : 1
}
</script>
