/**
 * First version of a new offer document, as HTML for Tiptap.
 *
 * The standard text comes from the Bausteine (notes of type EstimateBlock) with
 * the names below, so it stays editable in the settings. A missing Baustein is
 * left out. Everything ends up as normal content the user can change or delete;
 * the bound blocks fill in from the estimate.
 *
 * Offer content is German on purpose: it is the language of the document, not
 * of the user interface.
 */
export const DEFAULT_BAUSTEINE = [
  'Einleitung',
  'Hosting & Betreuung',
  'Nicht im Angebot enthalten',
  'Rahmenbedingungen',
  'Annahme',
]

export const DEFAULT_HOSTING_PACKAGES = [
  'Hosting Solo',
  'Businesscare Starter',
  'Businesscare Plus',
]

const CONTACT_KEY = 'angebot.contact'

/** The contact person last used in an offer on this computer. */
export function rememberedContact() {
  try {
    const stored = JSON.parse(window.localStorage.getItem(CONTACT_KEY) || '{}')
    return {
      contactName: String(stored.contactName || ''),
      contactEmail: String(stored.contactEmail || ''),
      contactPhone: String(stored.contactPhone || ''),
    }
  } catch {
    return { contactName: '', contactEmail: '', contactPhone: '' }
  }
}

export function rememberContact(contact) {
  try {
    window.localStorage.setItem(CONTACT_KEY, JSON.stringify(contact))
  } catch {
    // Private window or blocked storage: the field just is not prefilled next time.
  }
}

function block(type, attrs = {}) {
  const data = Object.entries(attrs)
    .map(([key, value]) => ` data-${key}='${JSON.stringify(value).replace(/'/g, '&#39;')}'`)
    .join('')

  return `<div data-node="${type}"${data}></div>`
}

/** The "du" version of a Baustein is a note named "<name> (du)". */
export const DU_SUFFIX = ' (du)'

export function bausteinFor(bausteine, name, address) {
  const byName = Object.fromEntries(bausteine.map((note) => [note.name, note]))
  return (address === 'du' && byName[name + DU_SUFFIX]) || byName[name] || null
}

export default function defaultDocument(bausteine = [], items = [], contact = {}, address = 'sie') {
  const baustein = (name) => bausteinFor(bausteine, name, address)?.notes || ''
  const hostingIds = DEFAULT_HOSTING_PACKAGES
    .map((name) => items.find((item) => item.name === name)?.id)
    .filter(Boolean)

  return [
    // Empty: the editor shows "Titel des Projekts" as a placeholder.
    '<h1></h1>',
    block('offerMeta', { ...contact, address }),
    baustein('Einleitung'),
    '<h2>Leistungsumfang</h2>',
    '<p>Das Projekt ist in Arbeitspakete gegliedert. Jedes Paket nennt die enthaltenen Tätigkeiten und den Betrag.</p>',
    block('estimateItems'),
    '<h2>Zeitplan</h2>',
    '<table><tbody>',
    '<tr><th><p>Phase</p></th><th><p>Start</p></th><th><p>Ende</p></th></tr>',
    '<tr><td><p></p></td><td><p>KW</p></td><td><p>KW</p></td></tr>',
    '<tr><td><p></p></td><td><p>KW</p></td><td><p>KW</p></td></tr>',
    '</tbody></table>',
    '<p>Der Zeitplan gilt ab Auftragserteilung. Er setzt voraus, dass Inhalte und Freigaben rechtzeitig vorliegen.</p>',
    '<h2>Kostenübersicht</h2>',
    block('estimateTotals'),
    '<p>Leistungen über den beschriebenen Umfang hinaus verrechnen wir nach Aufwand zum vereinbarten Stundensatz. Wir stimmen sie vorher schriftlich mit Ihnen ab.</p>',
    baustein('Hosting & Betreuung'),
    block('hostingPackages', { itemIds: hostingIds }),
    baustein('Nicht im Angebot enthalten'),
    baustein('Rahmenbedingungen'),
    baustein('Annahme'),
    block('signature'),
  ].join('')
}
