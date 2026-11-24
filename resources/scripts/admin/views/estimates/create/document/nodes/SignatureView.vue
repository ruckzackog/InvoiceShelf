<template>
  <BlockFrame type="signature" :selected="selected" :delete-node="deleteNode">
    <div class="doc-signature">
      <div>{{ $t('pdf_angebot_place_date') }}</div>
      <div>{{ $t('pdf_angebot_signature') }}</div>
    </div>
  </BlockFrame>
</template>

<script setup>
import { nodeViewProps } from '@tiptap/vue-3'
import BlockFrame from './BlockFrame.vue'

defineProps(nodeViewProps)
</script>
