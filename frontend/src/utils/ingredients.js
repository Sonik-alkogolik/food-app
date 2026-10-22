/**
 * Пересчёт ингредиентов под другое число порций.
 *
 * Количество в рецепте хранится обычными строками («Мука — 200 г»,
 * «1/2 стакана молока»), поэтому разбирать их приходится на клиенте:
 * сервер отдаёт список ингредиентов как есть, а пересчёт — это
 * умножение на коэффициент new/base, без запроса на сервер.
 */

// «Ингредиент — 200 г» и «Ингредиент - 200 г»: разделителем может быть
// тире, короткое тире или дефис. Дробь («1/2») проверяется раньше целого
// числа, иначе «1/2» прочиталось бы как «1» с остатком «/2».
const WITH_SEPARATOR = /^(.*?\s*[—–-]\s*)(\d+\/\d+|\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?)(\s*.*)$/
// «200 г муки»: количество в начале строки.
const LEADING_NUMBER = /^(\d+\/\d+|\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?)(\s*.*)$/

// Дроби, которые читаются лучше десятичной записи.
const NICE_FRACTIONS = [
  { value: 1 / 2, text: '1/2' },
  { value: 1 / 3, text: '1/3' },
  { value: 2 / 3, text: '2/3' },
  { value: 1 / 4, text: '1/4' },
  { value: 3 / 4, text: '3/4' },
]

/** «1 1/2», «1/2», «1,5» → число. Возвращает null, если разобрать нельзя. */
export function toNumber(raw) {
  const text = String(raw ?? '').trim().replace(',', '.')
  if (!text) return null

  let total = 0
  for (const part of text.split(/\s+/)) {
    if (!part) continue
    if (part.includes('/')) {
      const [num, den] = part.split('/').map((x) => parseFloat(x.replace(',', '.')))
      if (!Number.isFinite(num) || !Number.isFinite(den) || den === 0) return null
      total += num / den
    } else {
      const num = parseFloat(part)
      if (!Number.isFinite(num)) return null
      total += num
    }
  }

  return total
}

/**
 * Разбирает строку ингредиента на части.
 *
 * @returns {{ prefix: string, numberText: string, suffix: string, quantity: number }|null}
 *   null — количества в строке нет («Соль, перец — по вкусу»), строка
 *   остаётся как есть.
 */
function splitIngredient(line) {
  const text = String(line ?? '').trim()
  if (!text) return null

  const dashed = text.match(WITH_SEPARATOR)
  if (dashed) {
    const quantity = toNumber(dashed[2])
    if (quantity !== null) {
      return { prefix: dashed[1], numberText: dashed[2], suffix: dashed[3], quantity }
    }
  }

  const leading = text.match(LEADING_NUMBER)
  if (leading) {
    const quantity = toNumber(leading[1])
    if (quantity !== null) {
      return { prefix: '', numberText: leading[1], suffix: leading[2], quantity }
    }
  }

  return null
}

/** Число → текст: без лишних нулей, с запятой как в русских рецептах. */
export function formatQuantity(value) {
  const rounded = Math.round(value * 100) / 100
  if (Number.isInteger(rounded)) return String(rounded)

  for (const fraction of NICE_FRACTIONS) {
    if (Math.abs(rounded - fraction.value) < 0.02) return fraction.text
  }

  return String(rounded).replace('.', ',')
}

/**
 * Пересчитывает строку ингредиента под коэффициент.
 *
 * @param {string} line исходная строка
 * @param {number} factor new/base
 */
export function scaleIngredient(line, factor) {
  const text = String(line ?? '').trim()
  if (!Number.isFinite(factor) || factor === 1) return text

  const parts = splitIngredient(text)
  if (!parts) return text

  const quantity = formatQuantity(parts.quantity * factor)
  return `${parts.prefix}${quantity}${parts.suffix}`
}

/** Пересчитывает весь список ингредиентов. */
export function scaleIngredients(list, factor) {
  return (list || []).map((line) => scaleIngredient(line, factor))
}
