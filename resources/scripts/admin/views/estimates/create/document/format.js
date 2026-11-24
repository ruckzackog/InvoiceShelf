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

export function workPackage(description) {
  const intro = []
  const rows = []

  for (const line of descriptionLines(description)) {
    const index = line.indexOf(':')

    if (index === -1 && rows.length === 0) {
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
