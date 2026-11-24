<template>
  <BlockFrame type="offerMeta" :selected="selected" :delete-node="deleteNode">
    <div class="doc-meta">
      <p class="doc-meta-line">
        {{ $t('pdf_estimate_number') }} {{ estimate.estimate_number }}
        &nbsp;·&nbsp; {{ $t('pdf_estimate_date') }} {{ formatDocDate(estimate.estimate_date) }}
        <template v-if="estimate.expiry_date">
          &nbsp;·&nbsp; {{ $t('pdf_estimate_expire_date') }} {{ formatDocDate(estimate.expiry_date) }}
        </template>
      </p>
      <div class="doc-meta-columns">
        <div class="doc-address">
          <p class="doc-label">{{ $t('pdf_angebot_for') }}</p>
          <p v-for="line in billingLines" :key="line">{{ line }}</p>
        </div>
        <div class="doc-address">
          <p class="doc-label">{{ $t('pdf_angebot_contact') }}</p>
          <input
            v-for="key in contactKeys"
            :key="key"
            class="doc-inline-input"
            :value="node.attrs[key]"
            :placeholder="$t(`angebot_document.${placeholders[key]}`)"
            @input="setContact(key, $event.target.value)"
          />
        </div>
      </div>
    </div>
  </BlockFrame>
</template>

<script setup>
import { computed } from 'vue'
import { nodeViewProps } from '@tiptap/vue-3'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import BlockFrame from './BlockFrame.vue'
import { formatDocDate } from '../format'
import { rememberContact } from '../defaultDocument'

const props = defineProps(nodeViewProps)

function setContact(key, value) {
  props.updateAttributes({ [key]: value })
  rememberContact({ ...props.node.attrs, [key]: value })
}

const estimateStore = useEstimateStore()
const estimate = computed(() => estimateStore.newEstimate)

const contactKeys = ['contactName', 'contactEmail', 'contactPhone']
const placeholders = {
  contactName: 'contact_name',
  contactEmail: 'contact_email',
  contactPhone: 'contact_phone',
}

const billingLines = computed(() => {
  const customer = estimate.value.customer
  if (!customer) return []
  const billing = customer.billing || customer.billing_address || {}

  return [
    billing.name || customer.name,
    billing.address_street_1,
    billing.address_street_2,
    [billing.zip, billing.city].filter(Boolean).join(' '),
  ].filter(Boolean)
})
</script>
