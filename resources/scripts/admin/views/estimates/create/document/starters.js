/**
 * Starting points for a new offer. Each lists the work packages it starts with,
 * by item name: price, unit and description come from the items (Artikel). A
 * name without an item starts as an empty package with that name.
 */
export const STARTERS = {
  website: {
    icon: 'GlobeAltIcon',
    packages: [
      'Planung und Anforderungsanalyse',
      'Design und Prototyping',
      'Technische Entwicklung',
      'Launch und Go-Live',
    ],
  },
  shop: {
    icon: 'ShoppingCartIcon',
    packages: [
      'Planung und Anforderungsanalyse',
      'Design und Prototyping',
      'Technische Entwicklung',
      'Projektmanagement und Qualitätssicherung',
      'Launch und Go-Live',
    ],
  },
  custom: {
    icon: 'CodeBracketIcon',
    packages: [
      'Planung und Anforderungsanalyse',
      'Technische Entwicklung',
      'Projektmanagement und Qualitätssicherung',
    ],
  },
  empty: {
    icon: 'DocumentIcon',
    packages: [],
  },
}
