<template>
  <div class="doc-editor">
    <div v-if="editor" class="doc-toolbar">
      <div class="doc-toolbar-group">
        <button type="button" class="doc-icon" :title="$t('angebot_document.undo')" @click="run((c) => c.undo())">
          <ArrowUturnLeftIcon />
        </button>
        <button type="button" class="doc-icon" :title="$t('angebot_document.redo')" @click="run((c) => c.redo())">
          <ArrowUturnRightIcon />
        </button>
      </div>

      <BaseDropdown width-class="w-48">
        <template #activator>
          <span class="doc-style-select" :title="$t('angebot_document.paragraph')">
            {{ currentStyle.label }}
            <ChevronDownIcon />
          </span>
        </template>
        <BaseDropdownItem v-for="style in styles" :key="style.key" @click="run(style.apply)">
          <span :class="style.preview">{{ style.label }}</span>
        </BaseDropdownItem>
      </BaseDropdown>

      <div class="doc-toolbar-group">
        <button
          v-for="mark in marks"
          :key="mark.name"
          type="button"
          class="doc-icon"
          :title="$t(`angebot_document.${mark.name}`)"
          :class="{ active: editor.isActive(mark.name) }"
          @click="run(mark.toggle)"
        >
          <component :is="mark.icon" />
        </button>
      </div>

      <div class="doc-toolbar-group">
        <button type="button" class="doc-icon" :title="$t('angebot_document.bullet_list')" :class="{ active: editor.isActive('bulletList') }" @click="run((c) => c.toggleBulletList())">
          <ListBulletIcon />
        </button>
        <button type="button" class="doc-icon" :title="$t('angebot_document.ordered_list')" :class="{ active: editor.isActive('orderedList') }" @click="run((c) => c.toggleOrderedList())">
          <NumberedListIcon />
        </button>
      </div>

      <div class="doc-toolbar-group">
        <button
          v-for="align in alignments"
          :key="align.value"
          type="button"
          class="doc-icon"
          :title="$t(`angebot_document.align_${align.value}`)"
          :class="{ active: editor.isActive({ textAlign: align.value }) }"
          @click="run((c) => c.setTextAlign(align.value))"
        >
          <component :is="align.icon" />
        </button>
      </div>

      <!-- Only while the cursor is in a table -->
      <BaseDropdown v-if="editor.isActive('table')" width-class="w-48">
        <template #activator>
          <span class="doc-style-select doc-table-select">
            <TableCellsIcon />
            {{ $t('angebot_document.table') }}
            <ChevronDownIcon />
          </span>
        </template>
        <BaseDropdownItem @click="run((c) => c.addRowAfter())">{{ $t('angebot_document.add_row') }}</BaseDropdownItem>
        <BaseDropdownItem @click="run((c) => c.addColumnAfter())">{{ $t('angebot_document.add_column') }}</BaseDropdownItem>
        <BaseDropdownItem @click="run((c) => c.deleteRow())">{{ $t('angebot_document.delete_row') }}</BaseDropdownItem>
        <BaseDropdownItem @click="run((c) => c.deleteColumn())">{{ $t('angebot_document.delete_column') }}</BaseDropdownItem>
        <BaseDropdownItem @click="run((c) => c.deleteTable())">{{ $t('angebot_document.delete_table') }}</BaseDropdownItem>
      </BaseDropdown>

      <div class="doc-toolbar-end">
        <BaseDropdown width-class="w-72">
          <template #activator>
            <span class="doc-insert-button" @click="loadBausteine">
              <PlusIcon />
              {{ $t('angebot_document.insert') }}
              <ChevronDownIcon />
            </span>
          </template>
          <div class="doc-menu-section">{{ $t('angebot_document.insert_block') }}</div>
          <BaseDropdownItem v-for="type in BLOCK_TYPES" :key="type" @click="insertBlock(type)">
            {{ $t(`angebot_document.blocks.${type}`) }}
          </BaseDropdownItem>
          <BaseDropdownItem @click="run((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))">
            {{ $t('angebot_document.table') }}
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
import { computed, markRaw, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ArrowUturnLeftIcon,
  ArrowUturnRightIcon,
  Bars3BottomLeftIcon,
  Bars3BottomRightIcon,
  Bars3Icon,
  BoldIcon,
  ChevronDownIcon,
  ItalicIcon,
  ListBulletIcon,
  NumberedListIcon,
  PlusIcon,
  TableCellsIcon,
  UnderlineIcon,
} from '@heroicons/vue/20/solid'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useNotesStore } from '@/scripts/admin/stores/note'
import { useItemStore } from '@/scripts/admin/stores/item'
import { BLOCK_TYPES, FIELD_KEYS, documentExtensions } from './extensions'
import defaultDocument from './defaultDocument'
import '@/css/angebot-fonts.css'
import '@/css/angebot-document.css'

const emit = defineEmits(['edit-positions'])

const { t } = useI18n()

const estimateStore = useEstimateStore()
const companyStore = useCompanyStore()
const notesStore = useNotesStore()
const itemStore = useItemStore()

const marks = [
  { name: 'bold', icon: markRaw(BoldIcon), toggle: (c) => c.toggleBold() },
  { name: 'italic', icon: markRaw(ItalicIcon), toggle: (c) => c.toggleItalic() },
  { name: 'underline', icon: markRaw(UnderlineIcon), toggle: (c) => c.toggleUnderline() },
]

const alignments = [
  { value: 'left', icon: markRaw(Bars3BottomLeftIcon) },
  { value: 'center', icon: markRaw(Bars3Icon) },
  { value: 'right', icon: markRaw(Bars3BottomRightIcon) },
]

const styles = computed(() => [
  { key: 'paragraph', label: t('angebot_document.paragraph'), preview: 'text-sm', apply: (c) => c.setParagraph() },
  { key: 'h1', level: 1, label: t('angebot_document.heading_1'), preview: 'text-lg font-semibold', apply: (c) => c.setHeading({ level: 1 }) },
  { key: 'h2', level: 2, label: t('angebot_document.heading_2'), preview: 'text-base font-semibold', apply: (c) => c.setHeading({ level: 2 }) },
  { key: 'h3', level: 3, label: t('angebot_document.heading_3'), preview: 'text-sm font-semibold', apply: (c) => c.setHeading({ level: 3 }) },
])

const currentStyle = computed(() => {
  const active = styles.value.find((style) => style.level && editor.value?.isActive('heading', { level: style.level }))
  return active || styles.value[0]
})

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
.doc-toolbar {
  position: sticky;
  top: 72px;
  z-index: 20;
  display: flex;
  flex-wrap: wrap;
  gap: 4px 2px;
  align-items: center;
  padding: 6px;
  margin-bottom: 12px;
  background: #fff;
  border: 1px solid rgb(229 231 235);
  border-radius: 10px;
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
}

.doc-toolbar-group {
  display: flex;
  align-items: center;
  gap: 1px;
  padding: 0 4px;
  border-left: 1px solid rgb(229 231 235);
}

.doc-toolbar-group:first-child {
  padding-left: 0;
  border-left: 0;
}

.doc-toolbar button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  padding: 0 8px;
  font-size: 13px;
  color: rgb(55 65 81);
  border-radius: 6px;
  white-space: nowrap;
}

.doc-toolbar button.doc-icon {
  width: 32px;
  padding: 0;
}

.doc-toolbar button svg,
.doc-style-select svg,
.doc-insert-button svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.doc-toolbar button:hover {
  background: rgb(243 244 246);
}

.doc-toolbar button.active {
  background: var(--color-primary-50);
  color: var(--color-primary-600);
}

.doc-style-select {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  width: 136px;
  height: 32px;
  margin: 0 4px;
  padding: 0 8px 0 10px;
  font-size: 13px;
  color: rgb(55 65 81);
  border: 1px solid rgb(229 231 235);
  border-radius: 6px;
  cursor: pointer;
}

.doc-style-select:hover {
  border-color: rgb(209 213 219);
}

.doc-style-select svg {
  color: rgb(156 163 175);
}

.doc-table-select {
  width: auto;
  gap: 6px;
  border-color: var(--color-primary-200);
}

.doc-toolbar-end {
  margin-left: auto;
}

.doc-insert-button {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 32px;
  padding: 0 10px 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-primary-600);
  background: var(--color-primary-50);
  border-radius: 6px;
  cursor: pointer;
}

.doc-insert-button:hover {
  background: var(--color-primary-100);
}

.doc-menu-section {
  padding: 8px 16px 4px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgb(156 163 175);
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
/* Work packages edited in place */

.doc-package--editable {
  position: relative;
}

.doc-package-title {
  font-family: 'Angebot Josefin Sans', 'Josefin Sans', Arial, sans-serif !important;
  font-size: 11pt !important;
  font-weight: 700 !important;
  margin: 0 0 1.5mm;
}

.doc-package-intro,
.doc-activity textarea {
  min-height: 1.5em;
  resize: none;
  overflow: hidden;
  line-height: inherit;
}

.doc-package-intro {
  margin: 0 0 2mm;
}

.doc-activity td {
  position: relative;
}

.doc-activity-text {
  padding-right: 7mm !important;
}

.doc-row-remove,
.doc-package-remove {
  position: absolute;
  display: none;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  border-radius: 4px;
}

.doc-row-remove {
  top: 1.5mm;
  right: 0.5mm;
  width: 20px;
  height: 20px;
}

.doc-package-remove {
  top: -1mm;
  right: -1mm;
  width: 26px;
  height: 26px;
}

.doc-row-remove svg,
.doc-package-remove svg {
  width: 15px;
  height: 15px;
}

.doc-activity:hover .doc-row-remove,
.doc-package--editable:hover .doc-package-remove {
  display: inline-flex;
}

.doc-row-remove:hover,
.doc-package-remove:hover {
  color: #dc2626;
  background: #fef2f2;
}

.doc-add-row td {
  padding-top: 1mm !important;
  padding-bottom: 1mm !important;
  border-bottom: 0 !important;
}

.doc-link-button {
  font-family: ui-sans-serif, system-ui, sans-serif;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-primary-600);
}

.doc-link-button:hover {
  text-decoration: underline;
}

.doc-link-button--muted {
  color: #9ca3af;
  margin-left: auto;
}

.doc-add-package {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  margin: -3mm 0 6mm;
  padding: 2mm 3mm;
  border: 1px dashed #e5e7eb;
  border-radius: 6px;
}

.doc-from-items {
  max-width: 260px;
  padding: 2px 28px 2px 8px;
  font-family: ui-sans-serif, system-ui, sans-serif;
  font-size: 12px;
  color: #4b5563;
  border-color: #e5e7eb;
  border-radius: 6px;
}

.doc-price-inputs {
  white-space: nowrap;
}

.doc-price-inputs .doc-number {
  display: inline-block;
  width: 3.2em;
  text-align: right;
  font-weight: 400;
}

.doc-price-inputs .doc-price {
  width: 6.5em;
}
</style>
