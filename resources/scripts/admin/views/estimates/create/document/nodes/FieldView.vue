<template>
  <NodeViewWrapper as="span" class="doc-field" :class="{ 'doc-field--selected': selected, 'doc-field--empty': !value }">
    {{ value || $t(`angebot_document.fields.${node.attrs.key}`) }}
  </NodeViewWrapper>
</template>

<script setup>
import { computed } from 'vue'
import { NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { formatDocDate } from '../format'

const props = defineProps(nodeViewProps)

const estimateStore = useEstimateStore()
const companyStore = useCompanyStore()

const value = computed(() => {
  const estimate = estimateStore.newEstimate
  const customer = estimate.customer || {}

  switch (props.node.attrs.key) {
    case 'PRIMARY_CONTACT_NAME':
      return customer.contact_name
    case 'CONTACT_DISPLAY_NAME':
      return customer.name
    case 'ESTIMATE_NUMBER':
      return estimate.estimate_number
    case 'ESTIMATE_DATE':
      return formatDocDate(estimate.estimate_date)
    case 'ESTIMATE_EXPIRY_DATE':
      return formatDocDate(estimate.expiry_date)
    case 'COMPANY_NAME':
      return companyStore.selectedCompany?.name
    default:
      return ''
  }
})
</script>
