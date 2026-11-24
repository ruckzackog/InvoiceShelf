<template>
  <NodeViewWrapper
    class="doc-block"
    :class="{ 'doc-block--selected': selected }"
    :data-node="type"
  >
    <div class="doc-block-bar" contenteditable="false">
      <span class="doc-block-handle" data-drag-handle title="Ziehen">⠿</span>
      <span class="doc-block-label">{{ $t(`angebot_document.blocks.${type}`) }}</span>
      <slot name="actions" />
      <button type="button" class="doc-block-button" @click="deleteNode">
        {{ $t('angebot_document.remove_block') }}
      </button>
    </div>
    <div contenteditable="false">
      <slot />
    </div>
  </NodeViewWrapper>
</template>

<script setup>
import { NodeViewWrapper } from '@tiptap/vue-3'

defineProps({
  type: { type: String, required: true },
  selected: { type: Boolean, default: false },
  deleteNode: { type: Function, required: true },
})
</script>
