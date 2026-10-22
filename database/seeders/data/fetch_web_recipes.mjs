/**
 * Загрузчик рецептов из открытых интернет-источников.
 *
 * Источники:
 *   1. TheMealDB (themealdb.com) — 793 блюда, у каждого своё фото
 *      (strMealThumb), инструкции, ингредиенты с мерами и ссылка на видео.
 *   2. Food.com через датасет AkashPS11/recipes_data_food.com на HuggingFace —
 *      полнотекстовые рецепты с несколькими фото, временем, порциями,
 *      калорийностью и nutrition.
 *
 * Оба источника на английском, поэтому названия, ингредиенты и шаги
 * переводятся на русский через translate.googleapis.com (неофициальный
 * эндпоинт, работает без ключа).
 *
 * Перевод идёт БАТЧАМИ: за один запрос уходит до 20 строк, разделённых
 * маркером "<>". Это и быстрее, и главное — не упирается в rate limit
 * (HTTP 429), который официальный клиент "gtx" ловит уже после пары
 * десятков запросов подряд. Запасные клиенты — "dict-chrome-ex" и "at".
 *
 * Запуск:  node database/seeders/data/fetch_web_recipes.mjs [сколько]
 * Результат: database/seeders/data/web_recipes.json
 */

import { writeFileSync, readFileSync, existsSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const HERE = dirname(fileURLToPath(import.meta.url))
const OUT = join(HERE, 'web_recipes.json')
const CACHE = join(HERE, '.web_recipes_cache.json')
// Фотографии шагов для рецептов Food.com: у них кроме обложки есть ещё
// снимки процесса. Их использует ImageUrlSeeder.
const STEP_IMAGES = join(HERE, 'foodcom_images.json')
const TARGET = Number(process.argv[2] || 1100)

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
const log = (...a) => console.log(...a)

/* ---------------- перевод ---------------- */

/** Клиенты переводчика по очереди: gtx первой, остальные — запасные. */
const TRANSLATE_CLIENTS = ['gtx', 'dict-chrome-ex', 'at']

/** Сколько строк уходит в один запрос. */
const BATCH_SIZE = 20

/** Маркер между строками в батче. Не встречается в рецептах. */
const SEPARATOR = '<>'

const translateCache = new Map()
let translateCalls = 0
let translateItems = 0

/** Строки, которые перевод не смог — на них не ссылаемся повторно. */
const failedTranslations = new Set()

/**
 * Один запрос к переводчику. Возвращает переведённый текст.
 * При 429 переключается на следующего клиента, при прочих ошибках —
 * повторяет с нарастающей паузой.
 */
async function translateRequest(text, clientIndex = 0) {
  const client = TRANSLATE_CLIENTS[clientIndex % TRANSLATE_CLIENTS.length]
  const url =
    'https://translate.googleapis.com/translate_a/single?client=' + client +
    '&sl=en&tl=ru&dt=t&q=' + encodeURIComponent(text.slice(0, 4500))

  const res = await fetch(url, { signal: AbortSignal.timeout(30000) })

  // 429 — упёрлись в лимит клиента: пробуем другой клиент.
  if (res.status === 429 && clientIndex + 1 < TRANSLATE_CLIENTS.length) {
    return translateRequest(text, clientIndex + 1)
  }
  if (!res.ok) throw new Error('HTTP ' + res.status)

  const json = await res.json()
  const out = (json[0] || []).map((chunk) => chunk[0]).join('')
  if (!out) throw new Error('empty translation')
  return out
}

/**
 * Переводит одну строку en→ru. Результаты кэшируются на диске.
 */
async function translate(text) {
  if (!text || !/[a-zA-Z]/.test(text)) return text
  if (translateCache.has(text)) return translateCache.get(text)
  if (failedTranslations.has(text)) return text

  for (let attempt = 0; attempt < 4; attempt++) {
    try {
      const out = await translateRequest(text)
      translateCalls++
      translateItems++
      translateCache.set(text, out)
      return out
    } catch (err) {
      if (attempt === 3) {
        log('  ! перевод не удался, оставляем оригинал:', String(err.message).slice(0, 60))
        failedTranslations.add(text)
        translateCache.set(text, text)
        return text
      }
      await sleep(700 * (attempt + 1))
    }
  }
  return text
}

/**
 * Переводит массив строк БАТЧАМИ: за один запрос — до BATCH_SIZE строк.
 * Это в десятки раз меньше запросов, чем по одному, и не вызывает 429.
 */
async function translateAll(list) {
  const out = new Array(list.length)

  // Индексы строк, которые ещё не переведены и требуют перевода.
  const pending = []
  for (let i = 0; i < list.length; i++) {
    const text = list[i]
    if (!text || !/[a-zA-Z]/.test(text)) {
      out[i] = text
      continue
    }
    if (translateCache.has(text)) {
      out[i] = translateCache.get(text)
      continue
    }
    if (failedTranslations.has(text)) {
      out[i] = text
      continue
    }
    pending.push(i)
  }

  for (let start = 0; start < pending.length; start += BATCH_SIZE) {
    const chunk = pending.slice(start, start + BATCH_SIZE)
    const texts = chunk.map((i) => list[i])

    let translated = null
    for (let attempt = 0; attempt < 4 && translated === null; attempt++) {
      try {
        const joined = texts.join('\n' + SEPARATOR + '\n')
        const raw = await translateRequest(joined)
        const parts = raw.split(SEPARATOR)

        // Если переводчик «съел» маркер — переводим строки по одной.
        if (parts.length !== texts.length) {
          throw new Error('marker lost: ' + parts.length + ' != ' + texts.length)
        }

        translated = parts.map((p) => p.trim())
        translateCalls++
        translateItems += texts.length
      } catch (err) {
        if (attempt === 3) {
          log('  ! батч не перевёлся, порция по одной:', String(err.message).slice(0, 60))
          break
        }
        await sleep(900 * (attempt + 1))
      }
    }

    if (translated === null) {
      // Запасной путь — по одному, с кэшем.
      for (let k = 0; k < texts.length; k++) {
        out[chunk[k]] = await translate(texts[k])
      }
      continue
    }

    chunk.forEach((index, k) => {
      const value = translated[k] || texts[k]
      out[index] = value
      translateCache.set(texts[k], value)
    })

    // Пауза между батчами — чтобы не словить лимит.
    await sleep(350)
  }

  return out
}

/* ---------------- TheMealDB ---------------- */

const MEALDB_CATEGORIES = [
  'Beef', 'Breakfast', 'Chicken', 'Dessert', 'Goat', 'Lamb', 'Miscellaneous',
  'Pasta', 'Pork', 'Seafood', 'Side', 'Starter', 'Vegan', 'Vegetarian',
]

async function fetchMealDbIds() {
  log('TheMealDB: собираем ID блюд…')
  const ids = new Set()

  for (const c of MEALDB_CATEGORIES) {
    try {
      const res = await fetch(`https://www.themealdb.com/api/json/v1/1/filter.php?c=${c}`, {
        signal: AbortSignal.timeout(30000),
      })
      const json = await res.json()
      for (const m of json.meals || []) ids.add(m.idMeal)
    } catch (err) {
      log('  ! категория', c, 'не удалась:', String(err.message).slice(0, 50))
    }
    await sleep(150)
  }

  log('TheMealDB: уникальных ID —', ids.size)
  return [...ids]
}

/**
 * Приводит ссылку на видео к короткому embed-виду.
 * TheMealDB отдаёт обычные watch?v=... , но встречаются и /shorts/...,
 * и /embed/... — приводим всё к /embed/VIDEO_ID, чтобы плеер на сайте
 * работал одинаково.
 */
function normalizeVideo(url) {
  if (!url) return ''
  const match = String(url).match(/(?:v=|youtu\.be\/|shorts\/|embed\/)([A-Za-z0-9_-]{6,})/)
  if (!match) return ''
  return `https://www.youtube.com/embed/${match[1]}`
}

async function fetchMealDbDetails(id) {
  const res = await fetch(`https://www.themealdb.com/api/json/v1/1/lookup.php?i=${id}`, {
    signal: AbortSignal.timeout(30000),
  })
  const json = await res.json()
  const m = json.meals?.[0]
  if (!m) return null

  const ingredients = []
  for (let i = 1; i <= 20; i++) {
    const name = m[`strIngredient${i}`]
    if (!name) break
    const measure = (m[`strMeasure${i}`] || '').trim()
    ingredients.push({ name: name.trim(), measure })
  }

  // Инструкции приходят одним блоком с переводами строк — режем на шаги.
  const steps = (m.strInstructions || '')
    .split(/\r\n|\r|\n/)
    .map((s) => s.replace(/^\s*STEP\s*\d+\s*[-:.]?\s*/i, '').trim())
    .filter((s) => s.length > 12)

  return {
    source: 'mealdb',
    sourceId: String(m.idMeal),
    title: (m.strMeal || '').trim(),
    description: '',
    category: (m.strCategory || '').trim(),
    area: (m.strArea || '').trim(),
    tags: (m.strTags || '')
      .split(',')
      .map((t) => t.trim())
      .filter(Boolean),
    image: m.strMealThumb || '',
    video: normalizeVideo(m.strYoutube),
    ingredients,
    steps,
    cookTimeMinutes: null,
    servings: 4,
    calories: null,
  }
}

/* ---------------- Food.com (HuggingFace) ---------------- */

/** Разбирает R-строку вида c("a", "b") в массив. */
function parseRString(value) {
  if (!value) return []
  if (Array.isArray(value)) return value
  const s = String(value)
  try {
    // R-вектор: c("a", "b") → обрезаем оболочку и парсим как JSON-массив
    const inner = s.replace(/^c\(/, '').replace(/\)$/, '')
    const arr = JSON.parse('[' + inner + ']')
    return arr.map((v) => String(v).trim()).filter(Boolean)
  } catch {
    // запасной вариант — вытащить всё в кавычках
    const out = []
    const re = /"((?:[^"\\]|\\.)*)"/g
    let m
    while ((m = re.exec(s))) out.push(m[1].replace(/\\"/g, '"').trim())
    return out.filter(Boolean)
  }
}

/** ISO-8601 длительность ("PT9H", "PT1H30M") → минуты. */
function isoDurationToMinutes(value) {
  if (!value) return null
  const m = String(value).match(/^PT(?:(\d+)H)?(?:(\d+)M)?/i)
  if (!m) return null
  const h = Number(m[1] || 0)
  const min = Number(m[2] || 0)
  const total = h * 60 + min
  return total > 0 && total < 24 * 60 * 3 ? total : null
}

const HF_DATASET = 'AkashPS11/recipes_data_food.com'

async function fetchFoodComRows(offset, length) {
  const url =
    `https://datasets-server.huggingface.co/rows?dataset=${HF_DATASET}` +
    `&config=default&split=train&offset=${offset}&length=${length}`
  const res = await fetch(url, { signal: AbortSignal.timeout(60000) })
  if (!res.ok) throw new Error('HTTP ' + res.status)
  const json = await res.json()
  return json.rows || []
}

function foodComToRecipe(row) {
  const r = row.row
  const images = parseRString(r.Images)
  const parts = parseRString(r.RecipeIngredientParts)
  const quantities = parseRString(r.RecipeIngredientQuantities)
  const instructions = parseRString(r.RecipeInstructions)

  const ingredients = parts.map((name, i) => ({
    name,
    measure: quantities[i] || '',
  }))

  return {
    source: 'foodcom',
    sourceId: String(r.RecipeId),
    title: (r.Name || '').trim(),
    description: (r.Description || '').replace(/^Make and share this .*? recipe from Food\.com\.?$/i, '').trim(),
    category: (r.RecipeCategory || '').trim(),
    area: '',
    tags: (r.Keywords || '')
      .split(',')
      .map((t) => t.trim())
      .filter(Boolean)
      .slice(0, 5),
    image: images[0] || '',
    images,
    video: '',
    ingredients,
    steps: instructions.map((s) => s.trim()).filter((s) => s.length > 12),
    cookTimeMinutes: isoDurationToMinutes(r.TotalTime) || isoDurationToMinutes(r.CookTime),
    servings: Number(r.RecipeServings) || 4,
    calories: Number(r.Calories) || null,
  }
}

/* ---------------- основной поток ---------------- */

async function main() {
  // Подгружаем кэш переводов, чтобы повторный запуск не гонял заново.
  if (existsSync(CACHE)) {
    const saved = JSON.parse(readFileSync(CACHE, 'utf8'))
    for (const [k, v] of Object.entries(saved)) translateCache.set(k, v)
    log('Кэш переводов загружен:', translateCache.size)
  }

  const recipes = []

  // --- TheMealDB ---
  const mealIds = await fetchMealDbIds()
  for (let i = 0; i < mealIds.length; i++) {
    try {
      const r = await fetchMealDbDetails(mealIds[i])
      if (r && r.title && r.ingredients.length) recipes.push(r)
    } catch (err) {
      log('  ! mealdb', mealIds[i], String(err.message).slice(0, 40))
    }
    if ((i + 1) % 50 === 0) log(`  TheMealDB: ${i + 1}/${mealIds.length}`)
    await sleep(120)
  }
  log('TheMealDB: получено рецептов —', recipes.length)

  // --- Food.com: добираем до TARGET ---
  if (recipes.length < TARGET) {
    log('Food.com: добираем рецепты до', TARGET)
    let offset = 0
    while (recipes.length < TARGET && offset < 60000) {
      let rows = []
      try {
        rows = await fetchFoodComRows(offset, 100)
      } catch (err) {
        log('  ! foodcom offset', offset, String(err.message).slice(0, 50))
        await sleep(3000)
        continue
      }
      if (!rows.length) break

      for (const row of rows) {
        const r = foodComToRecipe(row)
        if (!r.title || !r.ingredients.length || !r.image) continue
        if (!r.steps.length) continue
        recipes.push(r)
        if (recipes.length >= TARGET) break
      }
      offset += 100
      log(`  Food.com: получено ${recipes.length} (offset ${offset})`)
      await sleep(250)
    }
  }

  log('Всего сырых рецептов:', recipes.length)

  // --- Дедупликация по названию ---
  const seen = new Set()
  const unique = []
  for (const r of recipes) {
    const key = r.title.toLowerCase().replace(/[^a-zа-яё0-9]+/gi, '')
    if (!key || seen.has(key)) continue
    seen.add(key)
    unique.push(r)
  }
  log('После дедупликации:', unique.length)

  // --- Перевод (батчами) ---
  log('Переводим на русский…')
  const startedAt = Date.now()

  for (let i = 0; i < unique.length; i++) {
    const r = unique[i]

    // Название, категория и теги — одним батчем.
    const header = await translateAll([r.title, r.category, ...r.tags.slice(0, 4)])
    r.titleRu = header[0]
    r.categoryRu = header[1]
    r.tagsRu = header.slice(2)

    // Ингредиенты и шаги — отдельными батчами.
    r.ingredientNamesRu = await translateAll(r.ingredients.map((x) => x.name))
    r.stepsRu = await translateAll(r.steps)

    if ((i + 1) % 25 === 0) {
      const elapsed = Math.round((Date.now() - startedAt) / 1000)
      log(
        `  переведено ${i + 1}/${unique.length}` +
        ` (запросов: ${translateCalls}, строк: ${translateItems}, ${elapsed}с)`
      )
    }
  }

  writeFileSync(CACHE, JSON.stringify(Object.fromEntries(translateCache)), 'utf8')
  writeFileSync(OUT, JSON.stringify(unique), 'utf8')

  // Фото шагов для Food.com — отдельным файлом: сидер берёт отсюда
  // снимки процесса, а из web_recipes.json — только обложку.
  const stepImages = {}
  for (const r of unique) {
    if (r.source === 'foodcom' && Array.isArray(r.images) && r.images.length > 1) {
      stepImages[r.sourceId] = r.images
    }
  }
  writeFileSync(STEP_IMAGES, JSON.stringify(stepImages, null, 1) + '\n', 'utf8')
  log('Фото шагов →', STEP_IMAGES, 'рецептов:', Object.keys(stepImages).length)

  log('Готово →', OUT)
  log('Запросов к переводу:', translateCalls, '| переведено строк:', translateItems)
  log('С видео:', unique.filter((r) => r.video).length, 'из', unique.length)
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
