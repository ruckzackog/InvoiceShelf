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

  <BasePage class="relative estimate-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
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
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <!-- Offer document (template angebot-dokument): Dokument and Formular views.
           Both stay mounted: the form components compute the tax amounts. -->
      <div v-if="isDocumentTemplate" class="flex flex-wrap items-center gap-4 mt-8 mb-4">
        <div class="inline-flex p-1 bg-gray-100 rounded-lg">
          <button
            v-for="view in ['document', 'form']"
            :key="view"
            type="button"
            :class="[
              'px-4 py-1.5 text-sm font-medium rounded-md',
              documentView === view ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700',
            ]"
            @click="documentView = view"
          >
            {{ $t(`angebot_document.${view}`) }}
          </button>
        </div>
        <span class="text-sm text-gray-500">{{ $t('angebot_document.mode_hint') }}</span>
      </div>

      <EstimateDocumentEditor
        v-if="isDocumentTemplate"
        v-show="documentView === 'document'"
        :is-edit="isEdit"
        @edit-positions="documentView = 'form'"
        @preview="previewDocument"
      />

      <BaseScrollPane v-show="!isDocumentTemplate || documentView === 'form'">
        <!-- Estimate Items -->
        <Items
          :currency="estimateStore.newEstimate.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="estimateValidationScope"
          :store="estimateStore"
          store-prop="newEstimate"
        />

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
            <!-- Estimate Custom Notes -->
            <NoteFields
              :store="estimateStore"
              store-prop="newEstimate"
              :fields="estimateNoteFieldList"
              type="Estimate"
            />

            <!-- Estimate Custom Fields -->
            <EstimateCustomFields
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
import { computed, ref, watch, onMounted } from 'vue'
import { cloneDeep } from 'lodash'
import { useRoute, useRouter } from 'vue-router'
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
const documentView = ref('document')
const previewUrl = ref(null)

const isDocumentTemplate = computed(
  () => estimateStore.newEstimate.template_name === DOCUMENT_TEMPLATE
)

async function previewDocument() {
  if (!isEdit.value) {
    // A new estimate has no PDF yet: save it, the view page shows the PDF.
    return submitForm()
  }

  const res = await submitForm({ stay: true })
  if (res?.data?.data) {
    previewUrl.value = `/estimates/pdf/${res.data.data.unique_hash}?v=${Date.now()}`
  }
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
    return false
  }

  isSaving.value = true

  let data = cloneDeep({
    ...estimateStore.newEstimate,
    sub_total: estimateStore.getSubTotal,
    total: estimateStore.getTotal,
    tax: estimateStore.getTotalTax,
  })
  if (data.discount_per_item === 'YES') {
    data.items.forEach((item, index) => {
      if (item.discount_type === 'fixed'){
        data.items[index].discount = Math.round(item.discount * 100)
      }
    })
  }
  else {
    if (data.discount_type === 'fixed'){
      data.discount = Math.round(data.discount * 100)
    }
  }

  if (
    !estimateStore.newEstimate.tax_per_item === 'YES'
    && data.taxes.length
  ){
    data.tax_type_ids = data.taxes.map(_t => _t.tax_type_id)
  }

  const action = isEdit.value
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
