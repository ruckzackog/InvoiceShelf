/**
 * The frame of a new offer: the work packages almost every project has, in
 * this order. Design and Technische Entwicklung are starting points to rename
 * and extend; project-specific packages go between them.
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
    name: 'Design und Prototyping',
    description: [
      'Dieses Arbeitspaket umfasst die Gestaltung als klickbaren Prototyp.',
      '- Klickbarer Prototyp: Gestaltung der wichtigsten Seiten für Desktop und Smartphone',
      '- Abstimmung: Gemeinsames Durchgehen und eine Korrekturrunde',
    ].join('\n'),
  },
  {
    // The place for the project itself: rename it and add the activities.
    name: 'Technische Entwicklung',
    description: [
      'Dieses Arbeitspaket umfasst die technische Umsetzung auf Basis des freigegebenen Designs.',
      '- Einrichtung: System, Hosting und Staging-Umgebung aufsetzen',
      '- Umsetzung: Seitenvorlagen und Funktionen nach freigegebenem Design',
      '- Inhalte: Übernahme der gelieferten Texte und Bilder',
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
