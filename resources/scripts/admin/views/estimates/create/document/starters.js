/**
 * The frame of a new offer: the work packages almost every project starts and
 * ends with. The middle packages are specific to each project and are written
 * in the document.
 *
 * Each package is taken from the item (Artikel) with that name when there is
 * one, so price and text can be maintained in InvoiceShelf. Without an item,
 * the text below is used and the price starts at 0.
 */
export const FRAME_PACKAGES = [
  {
    name: 'Konzept und Workshop',
    description: [
      'Dieses Arbeitspaket klärt die Anforderungen mit allen, die mit der Website arbeiten.',
      '- Workshop: Ziele, Zielgruppen und Inhalte gemeinsam klären und priorisieren',
      '- Seitenstruktur: Navigation und Seitentypen festlegen',
      '- Zusammenfassung: Ergebnisse schriftlich festhalten, als Grundlage für Design und Umsetzung',
    ].join('\n'),
  },
  {
    name: 'Test, Go-live und Einschulung',
    description: [
      'Dieses Arbeitspaket stellt sicher, dass alles zum Start fehlerfrei läuft.',
      '- Tests: Prüfung in aktuellen Browsern und auf Mobilgeräten',
      '- Go-live: Umstellung zu einem abgestimmten Zeitpunkt, Kontrolle nach dem Start',
      '- Einschulung: Online-Einschulung zur Pflege der Inhalte',
      '- Nachbetreuung: Zwei Wochen Fehlerbehebung nach dem Go-live ohne Zusatzkosten',
    ].join('\n'),
  },
]
