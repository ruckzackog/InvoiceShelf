<template>
  <textarea
    ref="field"
    rows="1"
    :value="modelValue"
    :placeholder="placeholder"
    @input="onInput"
  />
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const field = ref(null)

function resize() {
  if (!field.value) return
  field.value.style.height = 'auto'
  const height = field.value.scrollHeight
  // 0 while the editor is still hidden (Formular view) or not laid out yet:
  // keep the CSS height then, the next input or show resizes it.
  if (height > 0) {
    field.value.style.height = `${height}px`
  }
}

let observer = null

function onInput(event) {
  emit('update:modelValue', event.target.value)
  resize()
}

watch(() => props.modelValue, () => nextTick(resize))

onMounted(() => {
  requestAnimationFrame(resize)
  // Resize when the field becomes visible or the page width changes.
  observer = new ResizeObserver(() => resize())
  observer.observe(field.value)
})

onBeforeUnmount(() => observer?.disconnect())
</script>
