<template>
  <SelectTemplateModal />
  <ItemModal />
  <TaxTypeModal />
  <SalesTax
    v-if="salesTaxEnabled && (!isLoadingContent || route.query.customer)"
    :store="estimateStore"
    store-prop="newEstimate"
    :is-edit="isEdit"
    :customer="estimateStore.newEstimate.customer"
  />

  <OfferStartDialog
    v-if="showStartDialog && !isLoadingContent"
    @new="startNew"
    @copy="startCopy"
    @classic="startClassic"
  />

  <BasePage class="relative estimate-create-page">
    <form @submit.prevent="submitForm">
      <!-- Offer document (template angebot-dokument): its own header bar, no form above the page -->
      <div v-if="isDocumentTemplate" class="doc-header">
        <router-link to="/admin/estimates" class="doc-header-back" :title="$t('estimates.estimate', 2)">
          <BaseIcon name="ArrowLeftIcon" class="w-5 h-5" />
        </router-link>
        <div class="doc-header-title">
          <span class="text-lg font-semibold text-gray-900">
            {{ $t('pdf_estimate_label') }} {{ estimateStore.newEstimate.estimate_number }}
          </span>
          <span class="doc-status">{{ statusLabel }}</span>
          <span class="doc-save-state" :class="`doc-save-state--${saveState}`">
            <BaseIcon v-if="saveState === 'saved'" name="CheckIcon" class="w-4 h-4" />
            {{ saveStateLabel }}
          </span>
        </div>
        <div class="doc-header-actions">
          <BaseButton variant="primary-outline" type="button" :loading="isSaving && savingFor === 'preview'" @click="previewDocument">
            <template #left="slotProps">
              <BaseIcon :class="slotProps.class" name="EyeIcon" />
            </template>
            {{ $t('angebot_document.preview') }}
          </BaseButton>
          <BaseButton variant="primary" type="button" :loading="isSaving && savingFor === 'finish'" @click="finishDocument">
            {{ $t('angebot_document.finish') }}
          </BaseButton>
        </div>
      </div>

      <BasePageHeader v-else :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$t('estimates.estimate', 2)"
            to="/admin/estimates"
          />
          <BaseBreadcrumbItem
            v-if="$route.name === 'estimates.edit'"
            :title="$t('estimates.edit_estimate')"
            to="#"
            active
          />
          <BaseBreadcrumbItem
            v-else
            :title="$t('estimates.new_estimate')"
            to="#"
            active
          />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="$route.name === 'estimates.edit'"
            :to="`/estimates/pdf/${estimateStore.newEstimate.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            :content-loading="isLoadingContent"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                :class="slotProps.class"
                name="ArrowDownOnSquareIcon"
              />
            </template>
            {{ $t('estimates.save_estimate') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields  -->
      <EstimateBasicFields
        v-if="!isDocumentTemplate"
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <EstimateDocumentEditor
        v-if="isDocumentTemplate && !showStartDialog"
        :initial-address="startAddress"
        @open-settings="settingsOpen = true"
      >
        <template #panel>
          <section class="doc-card doc-card--customer">
            <BaseCustomerSelectPopup
              v-model="estimateStore.newEstimate.customer"
              :valid="v$.customer_id"
              :content-loading="isLoadingContent"
              type="estimate"
            />
          </section>

          <section class="doc-card">
            <div class="grid grid-cols-2 gap-3">
              <BaseInputGroup
                :label="$t('angebot_document.date')"
                :error="v$.estimate_date.$error && v$.estimate_date.$errors[0].$message"
              >
                <BaseDatePicker v-model="estimateStore.newEstimate.estimate_date" :content-loading="isLoadingContent" />
              </BaseInputGroup>
              <BaseInputGroup :label="$t('angebot_document.valid_until')">
                <BaseDatePicker v-model="estimateStore.newEstimate.expiry_date" :content-loading="isLoadingContent" />
              </BaseInputGroup>
            </div>
            <BaseInputGroup
              class="mt-3"
              :label="$t('estimates.estimate_number')"
              :error="v$.estimate_number.$error && v$.estimate_number.$errors[0].$message"
            >
              <BaseInput v-model="estimateStore.newEstimate.estimate_number" :content-loading="isLoadingContent" />
            </BaseInputGroup>
            <ExchangeRateConverter
              :store="estimateStore"
              store-prop="newEstimate"
              :v="v$"
              :is-loading="isLoadingContent"
              :is-edit="isEdit"
              :customer-currency="estimateStore.newEstimate.currency_id"
            />
          </section>

          <section class="doc-card doc-totals">
            <div class="doc-totals-row">
              <span>{{ $t('pdf_angebot_net') }}</span>
              <span>{{ money(netTotal) }}</span>
            </div>
            <div v-if="estimateStore.getTotalTax" class="doc-totals-row">
              <span>{{ $t('angebot_document.tax') }}</span>
              <span>{{ money(estimateStore.getTotalTax) }}</span>
            </div>
            <div class="doc-totals-row doc-totals-row--total">
              <span>{{ $t('pdf_angebot_gross') }}</span>
              <span>{{ money(estimateStore.getTotal) }}</span>
            </div>
            <button type="button" class="doc-link-button mt-2" @click="settingsOpen = true">
              {{ $t('angebot_document.settings_open') }}
            </button>
          </section>
        </template>
      </EstimateDocumentEditor>

      <!-- The regular form. For the offer document it lives in a side drawer and stays
           mounted while closed: the item and total components compute the tax amounts. -->
      <div
        :class="isDocumentTemplate ? ['doc-drawer', { 'doc-drawer--open': settingsOpen }] : ''"
        :aria-hidden="isDocumentTemplate && !settingsOpen"
      >
        <div v-if="isDocumentTemplate" class="doc-drawer-head">
          <span class="text-base font-semibold text-gray-900">{{ $t('angebot_document.settings_title') }}</span>
          <button type="button" class="doc-drawer-close" :aria-label="$t('angebot_document.close')" @click="settingsOpen = false">
            <BaseIcon name="XMarkIcon" class="w-5 h-5" />
          </button>
        </div>
      <BaseScrollPane :class="{ 'doc-scroll': isDocumentTemplate }">
        <!-- Estimate Items. In the drawer the table is folded away (still mounted:
             it computes the item taxes); the document edits the positions. -->
        <component :is="isDocumentTemplate ? 'details' : 'div'" class="doc-items-table">
          <summary v-if="isDocumentTemplate" class="doc-items-summary">
            {{ $t('angebot_document.items_table') }}
          </summary>
        <Items
          :currency="estimateStore.newEstimate.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="estimateValidationScope"
          :store="estimateStore"
          store-prop="newEstimate"
        />
        </component>

        <!-- Estimate Footer Section -->
        <div
          class="
            block
            mt-10
            estimate-foot
            lg:flex lg:justify-between lg:items-start
          "
        >
          <div class="relative w-full lg:w-1/2">
            <!-- The document template has its text in the document: the notes and the
                 custom fields of the old template do nothing there. v-if, not v-show:
                 unmounted, they send nothing, so stored values stay as they are. -->
            <p v-if="isDocumentTemplate" class="mb-6 text-sm text-gray-500">
              {{ $t('angebot_document.form_hint_document') }}
            </p>

            <!-- Estimate Custom Notes -->
            <NoteFields
              v-if="!isDocumentTemplate"
              :store="estimateStore"
              store-prop="newEstimate"
              :fields="estimateNoteFieldList"
              type="Estimate"
            />

            <!-- Estimate Custom Fields -->
            <EstimateCustomFields
              v-if="!isDocumentTemplate"
              type="Estimate"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :store="estimateStore"
              store-prop="newEstimate"
              :custom-field-scope="estimateValidationScope"
              class="mb-6"
            />

            <!-- Estimate Template Button-->
            <SelectTemplate
              :store="estimateStore"
              component-name="EstimateTemplate"
              store-prop="newEstimate"
              :is-mark-as-default="isMarkAsDefault"
            />
          </div>

          <Total
            :currency="estimateStore.newEstimate.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="estimateStore"
            store-prop="newEstimate"
            tax-popup-type="estimate"
          />
        </div>
      </BaseScrollPane>
      </div>
      <div v-if="isDocumentTemplate && settingsOpen" class="doc-drawer-backdrop" @click="settingsOpen = false" />
    </form>

    <div
      v-if="previewUrl"
      class="fixed inset-0 z-50 flex flex-col bg-black/60"
      @click.self="previewUrl = null"
    >
      <div class="flex items-center justify-between px-6 py-3 bg-white">
        <span class="font-medium">{{ $t('angebot_document.preview_title') }}</span>
        <BaseButton variant="primary-outline" type="button" @click="previewUrl = null">
          {{ $t('angebot_document.close') }}
        </BaseButton>
      </div>
      <iframe :src="previewUrl" class="flex-1 w-full bg-gray-200 border-0" />
    </div>
  </BasePage>
</template>

<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { cloneDeep, debounce } from 'lodash'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import http from '@/scripts/http'
import { useItemStore } from '@/scripts/admin/stores/item'
import { useI18n } from 'vue-i18n'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModuleStore } from '@/scripts/admin/stores/module'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'

import Items from '@/scripts/admin/components/estimate-invoice-common/CreateItems.vue'
import Total from '@/scripts/admin/components/estimate-invoice-common/CreateTotal.vue'
import SelectTemplate from '@/scripts/admin/components/estimate-invoice-common/SelectTemplateButton.vue'
import EstimateCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'
import NoteFields from '@/scripts/admin/components/estimate-invoice-common/CreateNotesField.vue'
import EstimateBasicFields from './EstimateCreateBasicFields.vue'
import SelectTemplateModal from '@/scripts/admin/components/modal-components/SelectTemplateModal.vue'
import TaxTypeModal from '@/scripts/admin/components/modal-components/TaxTypeModal.vue'
import ItemModal from '@/scripts/admin/components/modal-components/ItemModal.vue'
import SalesTax from '@/scripts/admin/components/estimate-invoice-common/SalesTax.vue'
import EstimateDocumentEditor from './document/EstimateDocumentEditor.vue'
import OfferStartDialog from './document/OfferStartDialog.vue'
import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'
import { formatDocMoney } from './document/format'
import { FRAME_PACKAGES } from './document/starters'

const estimateStore = useEstimateStore()
const moduleStore = useModuleStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()
const { t } = useI18n()

const estimateValidationScope = 'newEstimate'
let isSaving = ref(false)
const isMarkAsDefault = ref(false)

const estimateNoteFieldList = ref([
  'customer',
  'company',
  'customerCustom',
  'estimate',
  'estimateCustom',
])

let route = useRoute()
let router = useRouter()

let isLoadingContent = computed(() => estimateStore.isFetchingInitialSettings)

let pageTitle = computed(() =>
  isEdit.value ? t('estimates.edit_estimate') : t('estimates.new_estimate')
)

let isEdit = computed(() => route.name === 'estimates.edit')

const DOCUMENT_TEMPLATE = 'angebot-dokument'
const previewUrl = ref(null)
const settingsOpen = ref(false)
const savingFor = ref(null)
const itemStore = useItemStore()

const isDocumentTemplate = computed(
  () => estimateStore.newEstimate.template_name === DOCUMENT_TEMPLATE
)

const statusLabel = computed(() => {
  const status = String(estimateStore.newEstimate.status || 'DRAFT').toLowerCase()
  return t(`estimates.${status}`)
})

const netTotal = computed(() => {
  const estimate = estimateStore.newEstimate
  const discount = estimate.discount_per_item !== 'YES' ? estimate.discount_val : 0
  const included = estimate.tax_included ? estimateStore.getTotalTax : 0
  return estimateStore.getSubTotal - discount - included
})

function money(cents) {
  return formatDocMoney(cents, estimateStore.newEstimate.selectedCurrency || null)
}

// --- Start dialog (new offers only) ----------------------------------------

const showStartDialog = ref(!isEdit.value && !route.query.template)

const startAddress = ref('sie')

/** New offer: the frame packages around the project-specific ones. */
async function startNew(address) {
  startAddress.value = address
  estimateStore.newEstimate.template_name = DOCUMENT_TEMPLATE

  if (!itemStore.items.length || itemStore.items.length < itemStore.totalItems) {
    await itemStore.fetchItems({ limit: 'all' }).catch(() => {})
  }

  estimateStore.newEstimate.items = []
  FRAME_PACKAGES.forEach((frame, index) => {
    const article = itemStore.items.find((item) => item.name === frame.name)
    const price = article?.price || 0
    estimateStore.addItem()
    estimateStore.updateItem({
      ...estimateStore.newEstimate.items[index],
      index,
      name: frame.name,
      description: article?.description || frame.description,
      item_id: article?.id || null,
      unit_name: article?.unit?.name || null,
      price,
      quantity: 1,
      sub_total: price,
      total: price,
    })
  })

  showStartDialog.value = false
}

/**
 * Copy of an existing offer (document, positions, template) with a new number
 * and date, then open it. A full page load: the create page has no estimate.
 */
async function startCopy(id) {
  try {
    const response = await http.post(`/api/v1/estimates/${id}/clone`)
    window.location.assign(`/admin/estimates/${response.data.data.id}/edit`)
  } catch (err) {
    console.error(err)
  }
}

function startClassic() {
  showStartDialog.value = false
  if (estimateStore.newEstimate.template_name === DOCUMENT_TEMPLATE) {
    const other = estimateStore.templates.find((template) => template.name !== DOCUMENT_TEMPLATE)
    estimateStore.newEstimate.template_name = other?.name || 'estimate1'
  }
}

// --- Saving ----------------------------------------------------------------
// The offer document saves itself while you write (X-Autosave: no toast, no
// PDF). Vorschau and Fertig do a regular save that also renders the PDF.

const saveState = ref(isEdit.value ? 'saved' : 'new')
let loadedAt = null
let applyingServerData = false

const saveStateLabel = computed(() => t(`angebot_document.save_state.${saveState.value}`))

function buildPayload() {
  let data = cloneDeep({
    ...estimateStore.newEstimate,
    sub_total: estimateStore.getSubTotal,
    total: estimateStore.getTotal,
    tax: estimateStore.getTotalTax,
  })
  if (data.discount_per_item === 'YES') {
    data.items.forEach((item, index) => {
      if (item.discount_type === 'fixed') {
        data.items[index].discount = Math.round(item.discount * 100)
      }
    })
  } else if (data.discount_type === 'fixed') {
    data.discount = Math.round(data.discount * 100)
  }

  if (!estimateStore.newEstimate.tax_per_item === 'YES' && data.taxes.length) {
    data.tax_type_ids = data.taxes.map((_t) => _t.tax_type_id)
  }

  return data
}

async function autosave() {
  if (!isDocumentTemplate.value || isSaving.value) return

  if (v$.value.$invalid) {
    saveState.value = 'invalid'
    return
  }

  saveState.value = 'saving'
  const data = buildPayload()
  const headers = { 'X-Autosave': '1' }

  try {
    if (estimateStore.newEstimate.id) {
      await http.put(`/api/v1/estimates/${estimateStore.newEstimate.id}`, data, { headers })
    } else {
      const res = await http.post('/api/v1/estimates', data, { headers })
      applyingServerData = true
      estimateStore.newEstimate.id = res.data.data.id
      estimateStore.newEstimate.unique_hash = res.data.data.unique_hash
      estimateStore.newEstimate.status = res.data.data.status
      // Stay on this page; the address now points to the saved offer.
      window.history.replaceState(window.history.state, '', `/admin/estimates/${res.data.data.id}/edit`)
      await nextTick()
      applyingServerData = false
    }
    saveState.value = 'saved'
  } catch (err) {
    console.error(err)
    saveState.value = 'error'
  }
}

const scheduleAutosave = debounce(autosave, 2500)

watch(
  () => estimateStore.newEstimate,
  () => {
    if (!isDocumentTemplate.value || !loadedAt || applyingServerData) return
    // Ignore the burst of changes right after loading (components normalise data).
    if (Date.now() - loadedAt < 1500) return
    saveState.value = 'dirty'
    scheduleAutosave()
  },
  { deep: true }
)

watch(
  () => estimateStore.isFetchingInitialSettings,
  (loading) => {
    if (!loading) loadedAt = Date.now()
  },
  { immediate: true }
)

async function flushAutosave() {
  scheduleAutosave.cancel()
  if (saveState.value === 'dirty' || saveState.value === 'error') {
    await autosave()
  }
}

function warnUnsaved(event) {
  if (isDocumentTemplate.value && ['dirty', 'saving', 'error', 'invalid'].includes(saveState.value)) {
    event.preventDefault()
    event.returnValue = ''
  }
}

onMounted(() => window.addEventListener('beforeunload', warnUnsaved))
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', warnUnsaved)
  scheduleAutosave.cancel()
})
onBeforeRouteLeave(async () => {
  if (isDocumentTemplate.value) await flushAutosave()
})

async function previewDocument() {
  savingFor.value = 'preview'
  scheduleAutosave.cancel()
  const res = await submitForm({ stay: true })
  savingFor.value = null
  if (res?.data?.data) {
    saveState.value = 'saved'
    previewUrl.value = `/estimates/pdf/${res.data.data.unique_hash}?v=${Date.now()}`
  }
}

async function finishDocument() {
  savingFor.value = 'finish'
  scheduleAutosave.cancel()
  const res = await submitForm()
  savingFor.value = null
  if (res?.data?.data) saveState.value = 'saved'
}

const salesTaxEnabled = computed(() => {
  return (
    companyStore.selectedCompanySettings.sales_tax_us_enabled === 'YES' &&
    moduleStore.salesTaxUSEnabled
  )
})

const rules = {
  estimate_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  estimate_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  reference_number: {
    maxLength: helpers.withMessage(
      t('validation.price_maxlength'),
      maxLength(255)
    ),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  exchange_rate: {
    required: requiredIf(function () {
      helpers.withMessage(t('validation.required'), required)
      return estimateStore.showExchangeRate
    }),
    decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => estimateStore.newEstimate),
  { $scope: estimateValidationScope }
)

watch(
  () => estimateStore.newEstimate.customer,
  (newVal) => {
    if (newVal && newVal.currency) {
      estimateStore.newEstimate.selectedCurrency = newVal.currency
    } else {
      estimateStore.newEstimate.selectedCurrency =
        companyStore.selectedCompanyCurrency
    }
  }
)

estimateStore.resetCurrentEstimate()
customFieldStore.resetCustomFields()
v$.value.$reset
estimateStore.fetchEstimateInitialSettings(isEdit.value)

watch(
  () => companyStore.selectedCompanySettings?.tax_included_by_default,
  (newVal) => {
    estimateStore.newEstimate.tax_included = newVal === 'YES'
  },
  {immediate: true}
)

async function submitForm(options = {}) {
  const stay = options.stay === true

  v$.value.$touch()

  if (v$.value.$invalid) {
    if (isDocumentTemplate.value) {
      saveState.value = 'invalid'
      settingsOpen.value = !v$.value.customer_id.$invalid && !v$.value.estimate_number.$invalid
    }
    return false
  }

  isSaving.value = true

  const data = buildPayload()

  // After an autosave created the offer, the page is still the create route.
  const action = isEdit.value || estimateStore.newEstimate.id
    ? estimateStore.updateEstimate
    : estimateStore.addEstimate

  try {
    let res = await action(data)

    if (res.data.data && !stay) {
      router.push(`/admin/estimates/${res.data.data.id}/view`)
    }

    isSaving.value = false
    return res
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>

<style>
.doc-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 16px;
  margin-bottom: 20px;
}

.doc-header-back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  color: rgb(75 85 99);
  border: 1px solid rgb(229 231 235);
  border-radius: 8px;
  background: #fff;
}

.doc-header-back:hover {
  color: rgb(17 24 39);
  border-color: rgb(209 213 219);
}

.doc-header-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.doc-status {
  padding: 2px 10px;
  font-size: 12px;
  font-weight: 500;
  color: rgb(75 85 99);
  background: rgb(243 244 246);
  border-radius: 999px;
}

.doc-save-state {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  color: rgb(107 114 128);
}

.doc-save-state--saved {
  color: rgb(22 163 74);
}

.doc-save-state--invalid,
.doc-save-state--error {
  color: rgb(180 83 9);
}

.doc-header-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.doc-card--customer {
  padding: 0;
  border: 0;
  background: transparent;
}

.doc-card--customer > div {
  width: 100%;
}

/* The customer card of InvoiceShelf is built for a wide column: stack it. */
.doc-card--customer .justify-between.mb-2 {
  flex-wrap: wrap;
  gap: 4px 12px;
}

.doc-card--customer .truncate {
  white-space: normal;
}

.doc-card--customer [class*='max-w-'] {
  max-width: none;
}

.doc-card--customer .grid-cols-2 {
  grid-template-columns: minmax(0, 1fr);
  gap: 8px;
}

.doc-totals-row {
  display: flex;
  justify-content: space-between;
  padding: 3px 0;
  font-size: 13px;
  color: rgb(75 85 99);
}

.doc-totals-row--total {
  margin-top: 4px;
  padding-top: 6px;
  font-size: 15px;
  font-weight: 600;
  color: rgb(17 24 39);
  border-top: 1px solid rgb(229 231 235);
}

.doc-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  z-index: 30;
  width: min(760px, 100vw);
  padding: 0 24px 24px;
  overflow-x: hidden;
  overflow-y: auto;
  background: #fff;
  box-shadow: -12px 0 32px rgb(0 0 0 / 0.12);
  transform: translateX(105%);
  visibility: hidden;
  transition: transform 0.2s ease, visibility 0.2s;
}

.doc-drawer--open {
  transform: translateX(0);
  visibility: visible;
}

.doc-drawer-head {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0 -24px 8px;
  padding: 18px 24px 14px;
  background: #fff;
  border-bottom: 1px solid rgb(229 231 235);
}

.doc-drawer-close {
  display: inline-flex;
  padding: 6px;
  color: rgb(107 114 128);
  border-radius: 6px;
}

.doc-drawer-close:hover {
  background: rgb(243 244 246);
}

.doc-scroll > div > div > div {
  display: flex;
  flex-direction: column;
}

.doc-scroll .estimate-foot {
  order: -1;
  margin-top: 8px;
}

.doc-items-table[open] .doc-items-summary {
  margin-bottom: 12px;
}

.doc-items-summary {
  margin-top: 24px;
  padding: 10px 0;
  font-size: 14px;
  font-weight: 500;
  color: rgb(75 85 99);
  cursor: pointer;
  border-top: 1px solid rgb(229 231 235);
}

.doc-drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 29;
  background: rgb(55 65 81 / 0.2);
}
</style>
