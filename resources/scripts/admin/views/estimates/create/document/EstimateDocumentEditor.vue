<template>
  <div class="doc-editor">
    <div v-if="editor" class="doc-toolbar">
      <div class="doc-toolbar-group">
        <button type="button" :title="$t('angebot_document.undo')" @click="run((c) => c.undo())">↶</button>
        <button type="button" :title="$t('angebot_document.redo')" @click="run((c) => c.redo())">↷</button>
      </div>

      <div class="doc-toolbar-group">
        <button type="button" :class="{ active: editor.isActive('paragraph') }" @click="run((c) => c.setParagraph())">
          {{ $t('angebot_document.paragraph') }}
        </button>
        <button
          v-for="level in [1, 2, 3]"
          :key="level"
          type="button"
          :class="{ active: editor.isActive('heading', { level }) }"
          @click="run((c) => c.toggleHeading({ level }))"
        >
          {{ $t(`angebot_document.heading_${level}`) }}
        </button>
      </div>

      <div class="doc-toolbar-group">
        <button type="button" class="font-bold" :title="$t('angebot_document.bold')" :class="{ active: editor.isActive('bold') }" @click="run((c) => c.toggleBold())">B</button>
        <button type="button" class="italic" :title="$t('angebot_document.italic')" :class="{ active: editor.isActive('italic') }" @click="run((c) => c.toggleItalic())">I</button>
        <button type="button" class="underline" :title="$t('angebot_document.underline')" :class="{ active: editor.isActive('underline') }" @click="run((c) => c.toggleUnderline())">U</button>
      </div>

      <div class="doc-toolbar-group">
        <button type="button" :title="$t('angebot_document.bullet_list')" :class="{ active: editor.isActive('bulletList') }" @click="run((c) => c.toggleBulletList())">•&nbsp;{{ $t('angebot_document.bullet_list') }}</button>
        <button type="button" :title="$t('angebot_document.ordered_list')" :class="{ active: editor.isActive('orderedList') }" @click="run((c) => c.toggleOrderedList())">1.</button>
      </div>

      <div class="doc-toolbar-group">
        <button
          v-for="align in ['left', 'center', 'right']"
          :key="align"
          type="button"
          :title="$t(`angebot_document.align_${align}`)"
          :class="{ active: editor.isActive({ textAlign: align }) }"
          @click="run((c) => c.setTextAlign(align))"
        >
          {{ alignSymbols[align] }}
        </button>
      </div>

      <div class="doc-toolbar-group">
        <BaseDropdown width-class="w-56">
          <template #activator>
            <span class="doc-toolbar-menu">{{ $t('angebot_document.table') }} ▾</span>
          </template>
          <BaseDropdownItem @click="run((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))">
            {{ $t('angebot_document.table') }} 3 × 3
          </BaseDropdownItem>
          <template v-if="editor.isActive('table')">
            <BaseDropdownItem @click="run((c) => c.addRowAfter())">{{ $t('angebot_document.add_row') }}</BaseDropdownItem>
            <BaseDropdownItem @click="run((c) => c.addColumnAfter())">{{ $t('angebot_document.add_column') }}</BaseDropdownItem>
            <BaseDropdownItem @click="run((c) => c.deleteRow())">{{ $t('angebot_document.delete_row') }}</BaseDropdownItem>
            <BaseDropdownItem @click="run((c) => c.deleteColumn())">{{ $t('angebot_document.delete_column') }}</BaseDropdownItem>
            <BaseDropdownItem @click="run((c) => c.deleteTable())">{{ $t('angebot_document.delete_table') }}</BaseDropdownItem>
          </template>
        </BaseDropdown>

        <BaseDropdown width-class="w-72">
          <template #activator>
            <span class="doc-toolbar-menu doc-toolbar-insert" @click="loadBausteine">
              + {{ $t('angebot_document.insert') }} ▾
            </span>
          </template>
          <div class="doc-menu-section">{{ $t('angebot_document.insert_block') }}</div>
          <BaseDropdownItem v-for="type in BLOCK_TYPES" :key="type" @click="insertBlock(type)">
            {{ $t(`angebot_document.blocks.${type}`) }}
          </BaseDropdownItem>
          <div class="doc-menu-section">{{ $t('angebot_document.insert_baustein') }}</div>
          <BaseDropdownItem v-for="note in bausteine" :key="note.id" @click="insertBaustein(note)">
            {{ note.name }}
          </BaseDropdownItem>
          <p v-if="bausteineLoaded && !bausteine.length" class="px-4 py-2 text-xs text-gray-500">
            {{ $t('angebot_document.no_bausteine') }}
          </p>
          <div class="doc-menu-section">{{ $t('angebot_document.insert_field') }}</div>
          <BaseDropdownItem v-for="key in FIELD_KEYS" :key="key" @click="insertField(key)">
            {{ $t(`angebot_document.fields.${key}`) }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <div class="doc-toolbar-group doc-toolbar-end">
        <button type="button" class="doc-toolbar-primary" @click="$emit('preview')">
          {{ isEdit ? $t('angebot_document.preview') : $t('angebot_document.save_and_preview') }}
        </button>
      </div>
    </div>

    <div class="doc-canvas">
      <div class="angebot-doc doc-page">
        <div class="doc-letterhead">
          <div>
            <img v-if="company?.logo" class="doc-logo" :src="company.logo" :alt="company?.name" />
            <h3 v-else>{{ company?.name }}</h3>
          </div>
          <div class="doc-sender">
            <h3>{{ company?.name }}</h3>
            <p v-for="line in senderLines" :key="line">{{ line }}</p>
          </div>
        </div>

        <EditorContent :editor="editor" />

        <div class="doc-page-footer">{{ $t('angebot_document.footer_preview') }} · {{ $t('angebot_document.page_hint') }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useNotesStore } from '@/scripts/admin/stores/note'
import { useItemStore } from '@/scripts/admin/stores/item'
import { BLOCK_TYPES, FIELD_KEYS, documentExtensions } from './extensions'
import defaultDocument from './defaultDocument'
import '@/css/angebot-fonts.css'
import '@/css/angebot-document.css'

defineProps({
  isEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['edit-positions', 'preview'])

const estimateStore = useEstimateStore()
const companyStore = useCompanyStore()
const notesStore = useNotesStore()
const itemStore = useItemStore()

const alignSymbols = { left: '⇤', center: '↔', right: '⇥' }

const company = computed(() => companyStore.selectedCompany)
const senderLines = computed(() => {
  const address = company.value?.address || {}
  return [
    address.address_street_1,
    [address.zip, address.city].filter(Boolean).join(' '),
    address.phone,
  ].filter(Boolean)
})

const editor = useEditor({
  extensions: documentExtensions({ onEditPositions: () => emit('edit-positions') }),
  content: null,
  onUpdate: ({ editor }) => {
    estimateStore.newEstimate.document = editor.getJSON()
  },
})

let loading = false

async function loadDocument() {
  if (loading || !editor.value || estimateStore.isFetchingInitialSettings) {
    return
  }
  loading = true

  const document = estimateStore.newEstimate.document
  if (document && document.type === 'doc') {
    editor.value.commands.setContent(document, { emitUpdate: false })
    return
  }

  // New document: standard layout with the current Bausteine and hosting packages.
  await Promise.all([loadBausteine(), loadItems()]).catch(() => {})
  editor.value.commands.setContent(defaultDocument(bausteine.value, itemStore.items), { emitUpdate: false })
  estimateStore.newEstimate.document = editor.value.getJSON()
}

watch([editor, () => estimateStore.isFetchingInitialSettings], loadDocument, { immediate: true })

onBeforeUnmount(() => {
  editor.value?.destroy()
})

function run(command) {
  command(editor.value.chain().focus()).run()
}

function insertBlock(type) {
  run((c) => c.insertContent({ type }))
}

function insertField(key) {
  run((c) => c.insertContent({ type: 'field', attrs: { key } }))
}

const bausteine = ref([])
const bausteineLoaded = ref(false)

async function loadItems() {
  if (!itemStore.items.length || itemStore.items.length < itemStore.totalItems) {
    await itemStore.fetchItems({ limit: 'all' })
  }
}

async function loadBausteine() {
  if (bausteineLoaded.value) return
  const response = await notesStore.fetchNotes({ type: 'EstimateBlock', limit: 100 })
  bausteine.value = [...response.data.data].sort((a, b) => a.name.localeCompare(b.name, 'de'))
  bausteineLoaded.value = true
}

function insertBaustein(note) {
  // A copy: editing the Baustein later never changes an offer already written.
  run((c) => c.insertContent(note.notes))
}
</script>

<style>
.doc-editor {
  --doc-toolbar-height: 44px;
}

.doc-toolbar {
  position: sticky;
  top: 64px;
  z-index: 20;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  padding: 6px 8px;
  margin-bottom: 16px;
  background: #fff;
  border: 1px solid rgb(229 231 235);
  border-radius: 8px;
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
}

.doc-toolbar-group {
  display: flex;
  align-items: center;
  gap: 2px;
  padding-right: 6px;
  border-right: 1px solid rgb(229 231 235);
}

.doc-toolbar-group:last-child {
  border-right: 0;
}

.doc-toolbar-insert {
  font-weight: 600;
  color: var(--color-primary-600) !important;
}

.doc-menu-section {
  padding: 8px 16px 4px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgb(156 163 175);
}

.doc-toolbar-end {
  margin-left: auto;
}

.doc-toolbar button,
.doc-toolbar-menu {
  display: inline-flex;
  align-items: center;
  min-width: 28px;
  height: 30px;
  padding: 0 8px;
  font-size: 13px;
  color: rgb(55 65 81);
  border-radius: 6px;
  cursor: pointer;
  white-space: nowrap;
}

.doc-toolbar button:hover,
.doc-toolbar-menu:hover {
  background: rgb(243 244 246);
}

.doc-toolbar button.active {
  background: var(--color-primary-100);
  color: var(--color-primary-700);
}

.doc-toolbar .doc-toolbar-primary {
  color: #fff;
  background: var(--color-primary-500);
}

.doc-toolbar .doc-toolbar-primary:hover {
  background: var(--color-primary-600);
}

.doc-canvas {
  overflow-x: auto;
  padding: 24px 0 48px;
  background: rgb(243 244 246);
  border-radius: 8px;
}

.doc-page {
  min-height: 297mm;
  margin: 0 auto;
  padding-top: 15mm !important;
  padding-bottom: 12mm !important;
  box-shadow: 0 2px 12px rgb(0 0 0 / 0.08);
}

.doc-page .ProseMirror {
  outline: none;
  min-height: 150mm;
}

.doc-page .ProseMirror p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  color: #aaa;
  float: left;
  height: 0;
  pointer-events: none;
}

.doc-page .ProseMirror table .selectedCell {
  background: rgb(203 12 159 / 0.08);
}

.doc-page-footer {
  margin-top: 12mm;
  padding-top: 3mm;
  border-top: 1px dashed #d9d9d9;
  font-size: 7pt;
  color: #9a9a9a;
}

.doc-block {
  position: relative;
  margin: 0 -3mm 2mm;
  padding: 1mm 3mm;
  border: 1px dashed transparent;
  border-radius: 4px;
}

.doc-block:hover,
.doc-block--selected {
  border-color: rgb(203 12 159 / 0.45);
}

.doc-block-bar {
  position: absolute;
  top: -11px;
  right: 6px;
  display: none;
  gap: 4px;
  align-items: center;
  padding: 1px 4px;
  font-family: ui-sans-serif, system-ui, sans-serif;
  font-size: 11px;
  background: #fff;
  border: 1px solid rgb(203 12 159 / 0.45);
  border-radius: 4px;
}

.doc-block:hover .doc-block-bar,
.doc-block--selected .doc-block-bar {
  display: flex;
}

.doc-block-handle {
  cursor: grab;
  color: #999;
}

.doc-block-label {
  color: #cb0c9f;
  font-weight: 600;
}

.doc-block-button {
  padding: 0 6px;
  color: #555;
  border-radius: 3px;
}

.doc-block-button:hover {
  background: rgb(243 244 246);
}

.doc-block-button--primary {
  color: #cb0c9f;
}

.doc-block-panel {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 16px;
  padding: 3mm;
  margin-bottom: 3mm;
  font-family: ui-sans-serif, system-ui, sans-serif;
  font-size: 12px;
  background: rgb(249 250 251);
  border-radius: 4px;
}

.doc-block-option {
  display: flex;
  gap: 6px;
  align-items: center;
}

.doc-block-option--select {
  width: 100%;
}

.doc-block-option select {
  padding: 2px 24px 2px 6px;
  font-size: 12px;
  border-radius: 4px;
}

.doc-inline-input {
  display: block;
  width: 100%;
  padding: 0;
  margin: 0;
  font: inherit;
  color: inherit;
  background: transparent;
  border: 0;
  border-bottom: 1px dashed transparent;
}

.doc-inline-input:hover,
.doc-inline-input:focus {
  border-bottom-color: #cb0c9f;
  outline: none;
  box-shadow: none;
}

.doc-page-break-marker {
  padding: 1mm 0;
  font-size: 7.5pt;
  letter-spacing: 0.08em;
  text-align: center;
  text-transform: uppercase;
  color: #9a9a9a;
  border-top: 1px dashed #cb0c9f;
  border-bottom: 1px dashed #cb0c9f;
}

.doc-field {
  padding: 0 2px;
  background: rgb(203 12 159 / 0.08);
  border-radius: 3px;
}

.doc-field--empty {
  color: #cb0c9f;
}

.doc-field--selected {
  outline: 1px solid #cb0c9f;
}
</style>
