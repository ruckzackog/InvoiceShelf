<template>
  <BlockFrame type="pageBreak" :selected="selected" :delete-node="deleteNode">
    <div class="doc-page-break-marker">{{ $t('angebot_document.page_break') }}</div>
  </BlockFrame>
</template>

<script setup>
import { nodeViewProps } from '@tiptap/vue-3'
import BlockFrame from './BlockFrame.vue'

defineProps(nodeViewProps)
</script>
