import { Node, mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import { TableKit } from '@tiptap/extension-table'
import { Placeholder } from '@tiptap/extensions'
import OfferMetaView from './nodes/OfferMetaView.vue'
import ItemsView from './nodes/ItemsView.vue'
import TotalsView from './nodes/TotalsView.vue'
import HostingView from './nodes/HostingView.vue'
import SignatureView from './nodes/SignatureView.vue'
import PageBreakView from './nodes/PageBreakView.vue'
import FieldView from './nodes/FieldView.vue'

/**
 * Node types of the offer document. The names must match
 * App\Services\EstimateDocument\DocumentRenderer, which renders the PDF.
 * Bound blocks hold no numbers: they read the estimate store.
 */
function boundBlock(name, view, attributes = {}) {
  return Node.create({
    name,
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,

    addOptions() {
      return { onEditPositions: () => {} }
    },

    addAttributes() {
      return Object.fromEntries(
        Object.entries(attributes).map(([key, fallback]) => [
          key,
          {
            default: fallback,
            parseHTML: (element) => {
              const raw = element.getAttribute(`data-${key}`)
              if (raw === null) return fallback
              try {
                return JSON.parse(raw)
              } catch {
                return raw
              }
            },
            renderHTML: (attrs) => ({ [`data-${key}`]: JSON.stringify(attrs[key]) }),
          },
        ])
      )
    },

    parseHTML() {
      return [{ tag: `div[data-node="${name}"]` }]
    },

    renderHTML({ HTMLAttributes }) {
      return ['div', mergeAttributes(HTMLAttributes, { 'data-node': name })]
    },

    addNodeView() {
      return VueNodeViewRenderer(view)
    },
  })
}

export const OfferMeta = boundBlock('offerMeta', OfferMetaView, {
  contactName: '',
  contactEmail: '',
  contactPhone: '',
})
export const EstimateItems = boundBlock('estimateItems', ItemsView)
export const EstimateTotals = boundBlock('estimateTotals', TotalsView)
export const HostingPackages = boundBlock('hostingPackages', HostingView, {
  itemIds: [],
  recommendedId: null,
})
export const Signature = boundBlock('signature', SignatureView)
export const PageBreak = boundBlock('pageBreak', PageBreakView)

export const Field = Node.create({
  name: 'field',
  group: 'inline',
  inline: true,
  atom: true,
  selectable: true,

  addAttributes() {
    return {
      key: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-field'),
        renderHTML: (attrs) => ({ 'data-field': attrs.key }),
      },
    }
  },

  parseHTML() {
    return [{ tag: 'span[data-field]' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['span', HTMLAttributes]
  },

  addNodeView() {
    return VueNodeViewRenderer(FieldView)
  },
})

// Offered in the insert menu. Number and dates are in the offer details block;
// documents that already use other keys keep working (FieldView, renderer).
export const FIELD_KEYS = ['PRIMARY_CONTACT_NAME', 'CONTACT_DISPLAY_NAME']

export const BLOCK_TYPES = [
  'offerMeta',
  'estimateItems',
  'estimateTotals',
  'hostingPackages',
  'signature',
  'pageBreak',
]

export function documentExtensions({ onEditPositions, titlePlaceholder = '' }) {
  return [
    Placeholder.configure({
      // Only the title gets a hint; empty paragraphs stay clean.
      placeholder: ({ node }) => (node.type.name === 'heading' && node.attrs.level === 1 ? titlePlaceholder : ''),
      showOnlyCurrent: false,
    }),
    StarterKit.configure({
      // The PDF renderer has no code nodes: keep them out so nothing is lost.
      code: false,
      codeBlock: false,
      heading: { levels: [1, 2, 3] },
      link: { openOnClick: false, autolink: true },
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
      alignments: ['left', 'center', 'right', 'justify'],
    }),
    TableKit.configure({ table: { resizable: false } }),
    OfferMeta,
    EstimateItems.configure({ onEditPositions }),
    EstimateTotals.configure({ onEditPositions }),
    HostingPackages,
    Signature,
    PageBreak,
    Field,
  ]
}
