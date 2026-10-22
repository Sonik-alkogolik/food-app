import { ref } from 'vue'

/**
 * Режим готовки: крупный шрифт и негаснущий экран.
 *
 * Состояние живёт на уровне модуля, а не компонента: режим должен
 * переживать переходы между страницами — открыл рецепт, ушёл в категории
 * и вернулся, готовишь дальше.
 */
const active = ref(false)
const screenAwake = ref(false)
const wakeLockSupported = typeof navigator !== 'undefined' && 'wakeLock' in navigator

let wakeLock = null

function applyBodyClass() {
  document.body?.classList.toggle('cooking-mode', active.value)
}

async function requestWakeLock() {
  if (!wakeLockSupported || wakeLock) return

  try {
    const lock = await navigator.wakeLock.request('screen')
    wakeLock = lock
    screenAwake.value = true
    lock.addEventListener('release', () => {
      // Событие приходит и когда экран погас сам, и когда мы отпустили
      // блокировку вручную. Если блокировка уже заменена новой — не трогаем её.
      if (wakeLock === lock) {
        wakeLock = null
        screenAwake.value = false
      }
    })
  } catch {
    // Браузер может отказать: нет разрешения, вкладка в фоне, устройство
    // без поддержки. Это не ошибка — готовить можно и без блокировки.
    screenAwake.value = false
  }
}

async function releaseWakeLock() {
  if (!wakeLock) return
  try {
    await wakeLock.release()
  } catch {
    // Уже отпущена браузером — ничего делать не нужно.
  }
  wakeLock = null
  screenAwake.value = false
}

export function useCookingMode() {
  async function enable() {
    if (active.value) return
    active.value = true
    applyBodyClass()
    await requestWakeLock()
  }

  async function disable() {
    if (!active.value) return
    active.value = false
    applyBodyClass()
    await releaseWakeLock()
  }

  function toggle() {
    return active.value ? disable() : enable()
  }

  return { active, screenAwake, wakeLockSupported, enable, disable, toggle }
}

// Браузер снимает блокировку экрана, когда вкладка ушла в фон, поэтому
// при возврате её надо запросить заново — иначе экран погаснет, хотя
// пользователь готовит по открытому рецепту.
if (typeof document !== 'undefined') {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && active.value) requestWakeLock()
  })
  // Уход со страницы: блокировку нужно отпустить, иначе она держится
  // до перезагрузки.
  window.addEventListener('pagehide', () => {
    releaseWakeLock()
  })
}
