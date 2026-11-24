/**
 * Formatting shared by the document blocks. Mirrors
 * App\Services\EstimateDocument\DocumentRenderer so the editor shows what the
 * PDF will print.
 */

export function formatDocMoney(cents, currency) {
  const amount = (Number(cents) || 0) / 100
  const precision = currency ? Number(currency.precision) : 2
  const decimal = currency ? currency.decimal_separator : ','
  const thousand = currency ? currency.thousand_separator : '.'
  const symbol = currency ? currency.symbol : '€'

  const fixed = Math.abs(amount).toFixed(precision)
  let [whole, fraction] = fixed.split('.')
  whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousand)
  const number = (amount < 0 ? '-' : '') + whole + (fraction ? decimal + fraction : '')

  if (!currency || currency.swap_currency_symbol) {
    return `${number} ${symbol}`
  }

  return `${symbol} ${number}`
}

export function formatDocDate(value) {
  if (!value) {
    return ''
  }

  const date = new Date(String(value).slice(0, 10) + 'T00:00:00')

  if (Number.isNaN(date.getTime())) {
    return String(value)
  }

  return date.toLocaleDateString('de-AT', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

export function descriptionLines(description) {
  return String(description || '')
    .split(/\r\n|\r|\n/)
    .map((line) => line.replace(/^\s*[-•*]\s*/u, '').trim())
    .filter((line) => line !== '')
}

/**
 * Mirrors DocumentRenderer::workPackage(): rows start with "- " when the
 * description was written by the editor; older ones split on the first colon.
 */
export function workPackage(description) {
  const raw = String(description || '').split(/\r\n|\r|\n/)
  const rowPattern = /^\s*[-•*]\s+/u
  const marked = raw.some((line) => rowPattern.test(line))
  const intro = []
  const rows = []

  for (const original of raw) {
    const isRow = rowPattern.test(original)
    const line = original.replace(/^\s*[-•*]\s*/u, '').trim()
    if (line === '') continue

    const index = line.indexOf(':')
    const isIntro = marked ? !isRow : index === -1 && rows.length === 0

    if (isIntro) {
      intro.push(line)
      continue
    }

    rows.push(
      index === -1
        ? { title: line, text: '' }
        : { title: line.slice(0, index).trim(), text: line.slice(index + 1).trim() }
    )
  }

  return { intro, rows }
}

/**
 * Inverse of workPackage(): intro lines, then one "- Tätigkeit: Beschreibung"
 * line per row. Colons in a title would split it, so they are dropped there.
 */
export function packageDescription({ intro, rows }) {
  const introLines = intro.map((line) => line.trim()).filter(Boolean)
  const rowLines = rows
    .filter((row) => row.title.trim() || row.text.trim())
    .map((row) => {
      const title = row.title.replace(/:/g, '').trim()
      return row.text.trim() ? `- ${title}: ${row.text.trim()}` : `- ${title}`
    })

  return [...introLines, ...rowLines].join('\n')
}

export function parseMoneyInput(value) {
  const cleaned = String(value).replace(/[^0-9,.-]/g, '')
  // German input: "1.200,50" or "1200,5"; also accept "1200.50".
  const normalized = cleaned.includes(',')
    ? cleaned.replace(/\./g, '').replace(',', '.')
    : cleaned
  const amount = Number.parseFloat(normalized)

  return Number.isFinite(amount) ? Math.round(amount * 100) : 0
}

export function formatMoneyInput(cents) {
  return ((Number(cents) || 0) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}
