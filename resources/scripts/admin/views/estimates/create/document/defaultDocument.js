/**
 * First version of a new offer document. Everything is plain content the user
 * can change or delete; the bound blocks fill in from the estimate.
 */
export default function defaultDocument(t) {
  const text = (value) => [{ type: 'text', text: value }]

  return {
    type: 'doc',
    content: [
      { type: 'heading', attrs: { level: 1 }, content: text(t('angebot_document.default_title')) },
      { type: 'offerMeta', attrs: { contactName: '', contactEmail: '', contactPhone: '' } },
      { type: 'paragraph', content: text(t('angebot_document.default_greeting')) },
      { type: 'paragraph', content: text(t('angebot_document.default_intro')) },
      { type: 'heading', attrs: { level: 2 }, content: text(t('angebot_document.default_scope')) },
      { type: 'estimateItems' },
      { type: 'heading', attrs: { level: 2 }, content: text(t('angebot_document.default_costs')) },
      { type: 'estimateTotals' },
      { type: 'heading', attrs: { level: 2 }, content: text(t('angebot_document.default_acceptance')) },
      { type: 'paragraph', content: text(t('angebot_document.default_acceptance_text')) },
      { type: 'signature' },
    ],
  }
}
