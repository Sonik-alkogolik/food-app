<template>
  <div ref="container" class="kitchen-3d-container">
    <div v-if="!supported" class="fallback">
      <span class="fallback-emoji">🍳</span>
      <p>3D-кухня недоступна в этом браузере — подбор рецептов работает как обычно.</p>
    </div>
    <div class="vignette" aria-hidden="true"></div>
    <Transition name="hint">
      <div v-if="!inside" class="hint">👆 Кликните по холодильнику — заглянем внутрь</div>
    </Transition>
    <Transition name="hint">
      <button v-if="inside" class="back" @click="exitFridge">← Назад в кухню</button>
    </Transition>
  </div>
</template>

<script setup>
/* 3D-кухня: интерьер с холодильником. Клик по холодильнику приближает
   камеру к дверце, дверца распахивается, и мы оказываемся внутри, на полках
   с продуктами.

   Качество графики:
   — PBR-материалы (MeshStandardMaterial) с процедурными текстурами
     (швы, зерно дерева, кафель) вместо плоских цветов;
   — IBL-освещение через RoomEnvironment + PMREMGenerator: отражения и
     мягкая заполняющая тень от «окружения», а не только от ламп;
   — ACES-тонмаппинг и sRGB-цветовое пространство;
   — мягкие PCF-тени от направленного источника;
   — RectAreaLight у окна — мягкий направленный свет, как в настоящем
     помещении.

   Canvas живёт по размеру контейнера (ResizeObserver); GPU-ресурсы
   освобождаются при размонтировании; рендер встаёт на паузу, когда вкладка
   неактивна. */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  ACESFilmicToneMapping,
  AmbientLight,
  BoxGeometry,
  CanvasTexture,
  Color,
  CylinderGeometry,
  DirectionalLight,
  DoubleSide,
  Group,
  HemisphereLight,
  Mesh,
  MeshBasicMaterial,
  MeshPhysicalMaterial,
  MeshStandardMaterial,
  PCFShadowMap,
  PMREMGenerator,
  PerspectiveCamera,
  PlaneGeometry,
  PointLight,
  Raycaster,
  RectAreaLight,
  RepeatWrapping,
  SRGBColorSpace,
  Scene,
  SphereGeometry,
  TorusGeometry,
  Vector2,
  Vector3,
  WebGLRenderer,
} from 'three'
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js'
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js'
import { RectAreaLightUniformsLib } from 'three/addons/lights/RectAreaLightUniformsLib.js'
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js'
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js'
import { GTAOPass } from 'three/addons/postprocessing/GTAOPass.js'
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js'
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js'
import { ShaderPass } from 'three/addons/postprocessing/ShaderPass.js'
import { FXAAShader } from 'three/addons/shaders/FXAAShader.js'

const container = ref(null)
const supported = ref(true)
const inside = ref(false)

/** Ингредиенты, которые лежат у пользователя в холодильнике. */
const props = defineProps({
  items: { type: Array, default: () => [] },
})

let scene = null
let camera = null
let renderer = null
let fridge = null
let door = null
let insideLights = []
let shelvesGroup = null
let productsGroup = null
let frameId = null
let resizeObserver = null
let raycaster = null
let pointer = null
let transition = null
let lastTime = 0
let hoverFridge = false
let pmremGenerator = null
let envRT = null
let textures = []
let composer = null
let fxaaPass = null
let bloomPass = null

/** Положение камеры в кухне. */
const CAM_KITCHEN = { pos: new Vector3(0.4, 1.85, 5.2), look: new Vector3(-1.4, 1.25, -1.2) }
/** Точка перед дверцей — промежуточная станция подлёта. */
const CAM_DOOR_LOCAL_POS = new Vector3(0.25, 0.6, 2.1)
const CAM_DOOR_LOCAL_LOOK = new Vector3(0, 0.2, 0.2)
/** Точка внутри холодильника — в локальных координатах холодильника.
    Камера стоит снаружи дверного проёма примерно на уровне средней полки:
    так видны все три полки, а продукты не закрываются передним кантом. */
const CAM_INSIDE_LOCAL_POS = new Vector3(0, 0.18, 1.62)
const CAM_INSIDE_LOCAL_LOOK = new Vector3(0, 0.1, -0.5)

const camPos = CAM_KITCHEN.pos.clone()
const camLook = CAM_KITCHEN.look.clone()
const camPosTarget = CAM_KITCHEN.pos.clone()
const camLookTarget = CAM_KITCHEN.look.clone()
let camFov = 46
let camFovTarget = 46

/** Угол открытия дверцы (в радианах). Отрицательный — дверь распахивается
    в сторону комнаты, а не внутрь стены. */
const DOOR_OPEN = -2.05
const DOOR_CLOSED = 0
let doorTarget = DOOR_CLOSED
let doorAngle = DOOR_CLOSED

/** Декоративная начинка полок, если у пользователя пока пусто. */
const DECOR_ITEMS = [
  { color: 0xd8a13a, size: 0.165 },
  { color: 0xe8e2d6, size: 0.155 },
  { color: 0xc8752c, size: 0.15 },
  { color: 0xb14a3c, size: 0.14 },
  { color: 0x6a8f4a, size: 0.14 },
  { color: 0x8a5a32, size: 0.13 },
  { color: 0x9c3b34, size: 0.13 },
  { color: 0x6b4a86, size: 0.13 },
  { color: 0x3d6f96, size: 0.13 },
  { color: 0x2f8577, size: 0.13 },
  { color: 0xc98a2e, size: 0.115 },
  { color: 0xa8392f, size: 0.115 },
]

/** Палитра для реальных продуктов пользователя. */
const ITEM_COLORS = [
  0xd8a13a, 0xe8e2d6, 0xc8752c, 0xb14a3c, 0x6a8f4a, 0x8a5a32,
  0x9c3b34, 0x6b4a86, 0x3d6f96, 0x2f8577, 0xc98a2e, 0xa8392f,
  0x7a5c96, 0x4a5a6a, 0xd0cbc0, 0x4f8f3f,
]

/** Геометрия полок: y-координата (в локальных координатах холодильника). */
const SHELF_ROWS = [0.58, 0.04, -0.5]

/* ---------- процедурные текстуры ---------- */

/**
 * Генерирует текстуру по пиксельной функции.
 * Это дешевле внешних картинок и не требует загрузки ассетов.
 */
function makeTexture(size, paint, { repeat = 1, srgb = true } = {}) {
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d')
  paint(ctx, size)

  const texture = new CanvasTexture(canvas)
  if (srgb) texture.colorSpace = SRGBColorSpace
  texture.wrapS = texture.wrapT = RepeatWrapping
  texture.repeat.set(repeat, repeat)
  texture.anisotropy = renderer ? renderer.capabilities.getMaxAnisotropy() : 4
  textures.push(texture)

  return texture
}

/** Псевдослучайное число по индексу — детерминированная «зернистость». */
function noise(i) {
  const x = Math.sin(i * 127.1 + 311.7) * 43758.5453
  return x - Math.floor(x)
}

/** Зерно древесины: тёплые полосы с волокнами. */
function woodTexture(base = '#a9713f', dark = '#7d4f28', repeat = 3) {
  return makeTexture(256, (ctx, size) => {
    ctx.fillStyle = base
    ctx.fillRect(0, 0, size, size)

    for (let i = 0; i < 90; i++) {
      const y = noise(i) * size
      const amplitude = 6 + noise(i + 50) * 18
      const alpha = 0.05 + noise(i + 100) * 0.14
      ctx.strokeStyle = dark
      ctx.globalAlpha = alpha
      ctx.lineWidth = 1 + noise(i + 200) * 3
      ctx.beginPath()
      for (let x = 0; x <= size; x += 8) {
        const yy = y + Math.sin((x / size) * Math.PI * 4 + i) * amplitude
        x === 0 ? ctx.moveTo(x, yy) : ctx.lineTo(x, yy)
      }
      ctx.stroke()
    }
    ctx.globalAlpha = 1
  }, { repeat })
}

/** Кафельная плитка: сетка швов с лёгким градиентом. */
function tileTexture(base = '#e6e9e4', grout = '#c3c8c0', repeat = 4) {
  return makeTexture(256, (ctx, size) => {
    ctx.fillStyle = base
    ctx.fillRect(0, 0, size, size)

    const cells = 4
    const step = size / cells
    for (let y = 0; y < cells; y++) {
      for (let x = 0; x < cells; x++) {
        const shade = 236 - Math.floor(noise(y * cells + x) * 18)
        ctx.fillStyle = `rgb(${shade}, ${shade + 2}, ${shade - 4})`
        ctx.fillRect(x * step + 2, y * step + 2, step - 4, step - 4)
      }
    }

    ctx.strokeStyle = grout
    ctx.lineWidth = 3
    for (let i = 0; i <= cells; i++) {
      ctx.beginPath()
      ctx.moveTo(i * step, 0)
      ctx.lineTo(i * step, size)
      ctx.moveTo(0, i * step)
      ctx.lineTo(size, i * step)
      ctx.stroke()
    }
  }, { repeat })
}

/** Штукатурка стены: мелкое зерно поверх однотонной основы. */
function plasterTexture(base = '#efe7d8', repeat = 3) {
  return makeTexture(256, (ctx, size) => {
    ctx.fillStyle = base
    ctx.fillRect(0, 0, size, size)

    const image = ctx.getImageData(0, 0, size, size)
    const data = image.data
    for (let i = 0; i < data.length; i += 4) {
      const n = (noise(i * 0.37) - 0.5) * 12
      data[i] = Math.min(255, Math.max(0, data[i] + n))
      data[i + 1] = Math.min(255, Math.max(0, data[i + 1] + n))
      data[i + 2] = Math.min(255, Math.max(0, data[i + 2] + n))
    }
    ctx.putImageData(image, 0, 0)
  }, { repeat })
}

/**
 * Карта рельефа по уже готовой текстуре: та же картинка, но в линейном
 * пространстве. three.js сам выводит из неё нормали, поэтому швы плитки,
 * волокна дерева и штукатурка получают настоящий объём, а не плоскую
 * заливку. Это самый дешёвый способ убрать «мультяшность» без ассетов.
 */
function bumpFrom(source) {
  const bump = new CanvasTexture(source.image)
  bump.wrapS = bump.wrapT = RepeatWrapping
  bump.repeat.copy(source.repeat)
  bump.anisotropy = source.anisotropy
  textures.push(bump)
  return bump
}

/** Шлифованная сталь: горизонтальные риски. Используется как карта
    шероховатости — металл перестаёт выглядеть идеальным зеркалом. */
function brushedTexture(repeat = 2) {
  return makeTexture(256, (ctx, size) => {
    ctx.fillStyle = '#8c8c8c'
    ctx.fillRect(0, 0, size, size)

    for (let i = 0; i < 220; i++) {
      const y = noise(i) * size
      const shade = 120 + Math.floor(noise(i + 70) * 90)
      ctx.strokeStyle = `rgb(${shade}, ${shade}, ${shade})`
      ctx.globalAlpha = 0.35
      ctx.lineWidth = 1
      ctx.beginPath()
      ctx.moveTo(0, y)
      ctx.lineTo(size, y + (noise(i + 30) - 0.5) * 2)
      ctx.stroke()
    }
    ctx.globalAlpha = 1
  }, { repeat, srgb: false })
}

/** Ламинат на полу: продольные доски со стыками. */
function floorTexture(repeat = 6) {
  return makeTexture(256, (ctx, size) => {
    ctx.fillStyle = '#b3946a'
    ctx.fillRect(0, 0, size, size)

    const boards = 6
    const step = size / boards
    for (let i = 0; i < boards; i++) {
      const shade = 168 + Math.floor(noise(i) * 30)
      ctx.fillStyle = `rgb(${shade}, ${Math.floor(shade * 0.85)}, ${Math.floor(shade * 0.6)})`
      ctx.fillRect(i * step, 0, step - 1, size)

      // волокна доски
      ctx.globalAlpha = 0.1
      ctx.strokeStyle = '#6f5734'
      for (let k = 0; k < 12; k++) {
        const x = i * step + noise(i * 20 + k) * step
        ctx.lineWidth = 1
        ctx.beginPath()
        ctx.moveTo(x, 0)
        ctx.lineTo(x + noise(k) * 6, size)
        ctx.stroke()
      }
      ctx.globalAlpha = 1
    }
  }, { repeat })
}

/* ---------- вспомогательные конструкции ---------- */

/** Скруглённый бокс с корректными UV — для мебели и техники. */
function roundedBox(width, height, depth, radius, material) {
  const mesh = new Mesh(new RoundedBoxGeometry(width, height, depth, 3, radius), material)
  mesh.castShadow = true
  mesh.receiveShadow = true
  return mesh
}

/** Детерминированный вид продукта по его названию: цвет, размер и форма. */
function productStyle(name) {
  let h = 0
  for (let i = 0; i < name.length; i++) {
    h = (h * 31 + name.charCodeAt(i)) >>> 0
  }
  return {
    color: ITEM_COLORS[h % ITEM_COLORS.length],
    size: 0.135 + ((h >> 5) % 5) * 0.012,
    kind: (h >> 11) % 3, // 0 — коробка, 1 — банка, 2 — круглое
  }
}

/**
 * Раскладывает продукты по полкам: реальные ингредиенты пользователя —
 * сверху вниз, каждый со своей глубиной, чтобы изнутри они не сливались.
 */
function buildShelfLayout() {
  const rows = SHELF_ROWS.map(() => [])

  const real = props.items.map(productStyle)
  const decor = DECOR_ITEMS.map((d, i) => ({ ...d, kind: i % 3 }))
  const all = real.length ? real.concat(decor) : decor

  all.forEach((style, i) => {
    // глубину чередуем — продукты встают в три «ряда» по глубине.
    // Держим их ближе к переднему краю полки: сзади их перекрывает
    // передний кант, и предметы читаются как «половинки».
    style.z = [0.14, -0.04, 0.02][i % 3]
    // равномерно распределяем по полкам, начиная с верхней
    rows[Math.floor((i * rows.length) / all.length)].push(style)
  })

  return rows
}

/* ---------- сцена ---------- */

function initScene() {
  scene = new Scene()
  scene.background = new Color(0x1a1714)

  const { clientWidth: w, clientHeight: h } = container.value

  camera = new PerspectiveCamera(50, w / h, 0.05, 60)
  camera.position.copy(camPos)
  camera.lookAt(camLook)

  renderer = new WebGLRenderer({ antialias: true })
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
  // updateStyle=false — размер canvas задаёт CSS контейнера, а не inline-стиль three.js
  renderer.setSize(w, h, false)
  renderer.outputColorSpace = SRGBColorSpace
  renderer.toneMapping = ACESFilmicToneMapping
  renderer.toneMappingExposure = 0.95
  renderer.shadowMap.enabled = true
  renderer.shadowMap.type = PCFShadowMap
  container.value.appendChild(renderer.domElement)

  RectAreaLightUniformsLib.init()

  setupEnvironment()
  setupLights()
  buildRoom()
  buildFridge()
  setupPostProcessing()
}

/**
 * Постобработка: GTAO добавляет ambient occlusion — мягкое затемнение
 * в углах и под предметами. Именно это отличает фотореалистичную картинку
 * от «мультяшной»: без контактных теней объекты выглядят наклеенными.
 * FXAA убирает ступенчатость на краях.
 */
function setupPostProcessing() {
  const { clientWidth: w, clientHeight: h } = container.value
  const pixelRatio = renderer.getPixelRatio()

  composer = new EffectComposer(renderer)
  composer.setPixelRatio(pixelRatio)
  composer.setSize(w, h)

  composer.addPass(new RenderPass(scene, camera))

  const gtao = new GTAOPass(scene, camera, w, h)
  // OUTPUT.Default — AO смешивается с исходным изображением.
  // (Denoise/AO показывают только карту затенения, без цвета сцены.)
  gtao.output = GTAOPass.OUTPUT.Default
  gtao.blendIntensity = 0.9
  gtao.updateGtaoMaterial({
    radius: 0.22,
    distanceExponent: 1.6,
    thickness: 0.4,
    scale: 1.0,
    samples: 16,
  })
  gtao.updatePdMaterial({ lumaPhi: 8, depthPhi: 2.5, normalPhi: 3.5, radius: 4, rings: 2, samples: 8 })
  composer.addPass(gtao)

  // Bloom — лёгкое свечение вокруг ламп и бликов на металле.
  // Порог высокий (1.2), иначе «загораются» все светлые поверхности
  // и картинка выцветает — glow должен быть только у ярких источников.
  bloomPass = new UnrealBloomPass(new Vector2(w, h), 0.32, 0.55, 1.2)
  composer.addPass(bloomPass)

  composer.addPass(new OutputPass())

  fxaaPass = new ShaderPass(FXAAShader)
  fxaaPass.material.uniforms.resolution.value.set(1 / (w * pixelRatio), 1 / (h * pixelRatio))
  composer.addPass(fxaaPass)
}

/**
 * IBL: префиксированная карта окружения из RoomEnvironment.
 * Даёт материалам отражения и мягкую заполняющую тень — без неё
 * PBR-поверхности выглядят плоскими и «мультяшными».
 */
function setupEnvironment() {
  pmremGenerator = new PMREMGenerator(renderer)
  pmremGenerator.compileEquirectangularShader()

  const room = new RoomEnvironment()
  envRT = pmremGenerator.fromScene(room, 0.04)
  scene.environment = envRT.texture
  scene.environmentIntensity = 0.45

  room.dispose?.()
}

function setupLights() {
  // Заполняющий свет намеренно слабый. Раньше ambient + hemisphere давали
  // почти равномерную заливку — сцена выглядела плоской и «мультяшной».
  // Теперь объём создаёт направленный свет из окна, а IBL лишь подсвечивает
  // тени (см. environmentIntensity).
  scene.add(new AmbientLight(0xc8d6e2, 0.05))
  scene.add(new HemisphereLight(0xfff2dc, 0x2e2519, 0.12))

  // Основной источник — солнце из окна. Луч идёт СПРАВА-СЗАДИ (окно стоит
  // на правой стене) и падает на холодильник: так свет ложится на кухню
  // правдоподобно, а предметы получают светлую и тёмную стороны.
  const sun = new DirectionalLight(0xffeed0, 2.4)
  sun.position.set(5.2, 3.4, -2.4)
  sun.castShadow = true
  sun.shadow.mapSize.set(2048, 2048)
  sun.shadow.camera.near = 0.5
  sun.shadow.camera.far = 24
  sun.shadow.camera.left = -7
  sun.shadow.camera.right = 7
  sun.shadow.camera.top = 7
  sun.shadow.camera.bottom = -7
  sun.shadow.bias = -0.0006
  sun.shadow.normalBias = 0.025
  sun.shadow.radius = 3
  scene.add(sun)
  scene.add(sun.target)
  sun.target.position.set(-1.6, 0.4, 1.2)

  // Холодный отсвет от улицы — контровой свет со стороны окна
  const sky = new DirectionalLight(0xcfe4f5, 0.22)
  sky.position.set(3.2, 2.6, -6)
  scene.add(sky)

  // Мягкий свет прямо от оконного проёма
  const windowLight = new RectAreaLight(0xdcecfa, 3.2, 2.2, 1.8)
  windowLight.position.set(3.55, 2.4, -2.8)
  windowLight.lookAt(-1, 1.4, 1.2)
  scene.add(windowLight)

  // Тёплый отсвет от подвесных ламп над столом.
  // Без собственной тени: VSM/PCF-теней от точечного источника достаточно,
  // а лишние shadow-pass'ы тормозят сцену.
  const lampLight = new PointLight(0xffd9a0, 3.5, 6, 2)
  lampLight.position.set(0.8, 2.9, 0.6)
  scene.add(lampLight)
}

function buildRoom() {
  const room = new Group()

  const woodMap = woodTexture('#a9713f', '#714723', 3)
  const counterMap = woodTexture('#3f3a35', '#2a2622', 4)
  const wallMap = plasterTexture('#efe7d8', 3)
  const tileMap = tileTexture('#e2e6e0', '#bfc6bd', 8)
  const floorMap = floorTexture(6)
  const brushedMap = brushedTexture(2)

  // Карты рельефа дают материалам микрорельеф: швы плитки «вдавлены»,
  // волокна дерева проступают, штукатурка не выглядит идеально гладкой.
  const floorMaterial = new MeshStandardMaterial({
    map: floorMap,
    bumpMap: bumpFrom(floorMap),
    bumpScale: 0.35,
    roughness: 0.66,
    metalness: 0.02,
  })
  const wallMaterial = new MeshStandardMaterial({
    map: wallMap,
    bumpMap: bumpFrom(wallMap),
    bumpScale: 0.12,
    roughness: 0.94,
    metalness: 0,
  })
  const counterMaterial = new MeshStandardMaterial({
    map: counterMap,
    bumpMap: bumpFrom(counterMap),
    bumpScale: 0.2,
    roughness: 0.34,
    metalness: 0.05,
  })
  const cabinetMaterial = new MeshStandardMaterial({ color: 0xe4dccd, roughness: 0.5, metalness: 0.02 })
  const tileMaterial = new MeshStandardMaterial({
    map: tileMap,
    bumpMap: bumpFrom(tileMap),
    bumpScale: 0.5,
    roughness: 0.22,
    metalness: 0.02,
  })
  // Сталь со шлифовкой: карта шероховатости ломает зеркальный блик.
  // Металличность и шероховатость подобраны так, чтобы крупные плоскости
  // (микроволновка, вытяжка) не пересвечивались в белое пятно напротив окна.
  const steelMaterial = new MeshStandardMaterial({
    color: 0xa9aeb3,
    roughnessMap: brushedMap,
    roughness: 0.6,
    metalness: 0.72,
  })
  const chromeMaterial = new MeshStandardMaterial({ color: 0xc2c7cb, roughness: 0.3, metalness: 0.9 })

  // пол
  const floor = new Mesh(new BoxGeometry(16, 0.1, 12), floorMaterial)
  floor.position.set(0, -0.05, 0)
  floor.receiveShadow = true
  room.add(floor)

  // задняя стена
  const backWall = new Mesh(new BoxGeometry(16, 3.7, 0.1), wallMaterial)
  backWall.position.set(0, 1.85, -3)
  backWall.receiveShadow = true
  room.add(backWall)

  // потолок — без него сверху видно пустой чёрный фон.
  // Прямого света он не получает (свет падает сверху вниз), поэтому
  // добавляем лёгкое собственное свечение — потолок выглядит освещённым.
  const ceiling = new Mesh(
    new BoxGeometry(16, 0.1, 12),
    new MeshStandardMaterial({
      color: 0xf6f2ea,
      roughness: 0.96,
      emissive: 0x6a635a,
      emissiveIntensity: 1,
    })
  )
  ceiling.position.set(0, 3.6, 0)
  ceiling.receiveShadow = true
  room.add(ceiling)

  // потолочный светильник
  const ceilingLamp = new Mesh(
    new CylinderGeometry(0.5, 0.55, 0.1, 28),
    new MeshStandardMaterial({ color: 0xf2ede2, roughness: 0.7, emissive: 0xffe9c0, emissiveIntensity: 0.9 })
  )
  ceilingLamp.position.set(0.8, 3.5, 0.6)
  room.add(ceilingLamp)

  // левая стена
  const leftWall = new Mesh(new BoxGeometry(0.1, 3.7, 12), wallMaterial)
  leftWall.position.set(-4.5, 1.85, 0)
  leftWall.receiveShadow = true
  room.add(leftWall)

  // фартук из плитки
  const backsplash = new Mesh(new BoxGeometry(5.4, 0.55, 0.04), tileMaterial)
  backsplash.position.set(1.5, 1.25, -2.92)
  backsplash.receiveShadow = true
  room.add(backsplash)

  // кухонная столешница вдоль задней стены (правее холодильника)
  const counter = roundedBox(5.4, 0.1, 1.1, 0.03, counterMaterial)
  counter.position.set(1.5, 0.92, -2.45)
  counter.castShadow = true
  room.add(counter)

  // тумбы с фасадами
  const cabinets = roundedBox(5.3, 0.86, 1.0, 0.02, cabinetMaterial)
  cabinets.position.set(1.5, 0.43, -2.45)
  room.add(cabinets)

  // ручки-профили на фасадах
  const handleMaterial = new MeshStandardMaterial({ color: 0x6b6f74, roughness: 0.35, metalness: 0.85 })
  for (let i = 0; i < 4; i++) {
    const x = -0.75 + i * 1.32
    const doorFront = roundedBox(1.26, 0.76, 0.05, 0.015, cabinetMaterial)
    doorFront.position.set(x, 0.43, -1.92)
    room.add(doorFront)

    const handle = new Mesh(new BoxGeometry(0.5, 0.035, 0.035), handleMaterial)
    handle.position.set(x, 0.72, -1.87)
    handle.castShadow = true
    room.add(handle)
  }

  // мойка
  const sink = roundedBox(0.9, 0.28, 0.6, 0.04, steelMaterial)
  sink.position.set(-0.1, 0.85, -2.45)
  room.add(sink)

  const faucet = new Mesh(new CylinderGeometry(0.03, 0.03, 0.45, 12), chromeMaterial)
  faucet.position.set(-0.1, 1.15, -2.78)
  faucet.castShadow = true
  room.add(faucet)

  const faucetArc = new Mesh(new TorusGeometry(0.13, 0.028, 10, 20, Math.PI), chromeMaterial)
  faucetArc.position.set(-0.1, 1.37, -2.78)
  faucetArc.rotation.set(Math.PI / 2, 0, 0)
  room.add(faucetArc)

  // встроенная духовка
  const oven = roundedBox(0.66, 0.6, 0.62, 0.02, steelMaterial)
  oven.position.set(0.57, 0.43, -2.42)
  room.add(oven)

  const ovenGlass = new Mesh(
    new BoxGeometry(0.56, 0.36, 0.04),
    new MeshStandardMaterial({ color: 0x1b1f24, roughness: 0.12, metalness: 0.3 })
  )
  ovenGlass.position.set(0.57, 0.4, -2.1)
  room.add(ovenGlass)

  const ovenPanel = new Mesh(
    new BoxGeometry(0.6, 0.1, 0.05),
    new MeshStandardMaterial({ color: 0x2a2f34, roughness: 0.4, metalness: 0.5 })
  )
  ovenPanel.position.set(0.57, 0.66, -2.1)
  room.add(ovenPanel)

  // микроволновка
  const microwave = roundedBox(0.6, 0.36, 0.42, 0.02, steelMaterial)
  microwave.position.set(1.85, 2.45, -2.6)
  room.add(microwave)

  // верхние шкафы
  const wallCabinets = roundedBox(3.0, 0.85, 0.75, 0.02, cabinetMaterial)
  wallCabinets.position.set(0.6, 2.45, -2.6)
  room.add(wallCabinets)

  for (let i = 0; i < 3; i++) {
    const handle = new Mesh(new BoxGeometry(0.4, 0.03, 0.03), handleMaterial)
    handle.position.set(-0.45 + i * 0.55, 2.12, -2.2)
    room.add(handle)
  }

  // вытяжка
  const hood = roundedBox(1.2, 0.5, 0.7, 0.04, steelMaterial)
  hood.position.set(-0.9, 2.95, -2.6)
  room.add(hood)

  const hoodDuct = roundedBox(0.5, 0.6, 0.4, 0.03, steelMaterial)
  hoodDuct.position.set(-0.9, 3.4, -2.75)
  room.add(hoodDuct)

  // окно — справа, чтобы не перекрывалось холодильником
  const frame = roundedBox(2.2, 1.8, 0.12, 0.02, new MeshStandardMaterial({ color: 0xf6f6f4, roughness: 0.5 }))
  frame.position.set(3.6, 2.4, -2.92)
  room.add(frame)

  // стекло — слегка голубое, с отражением окружения
  const glass = new Mesh(
    new BoxGeometry(1.95, 1.55, 0.04),
    new MeshStandardMaterial({
      color: 0xbcd9e8,
      roughness: 0.08,
      metalness: 0.1,
      transparent: true,
      opacity: 0.55,
    })
  )
  glass.position.set(3.6, 2.4, -2.86)
  room.add(glass)

  // рама окна
  const mullion = new Mesh(new BoxGeometry(0.06, 1.55, 0.06), new MeshStandardMaterial({ color: 0xf6f6f4, roughness: 0.5 }))
  mullion.position.set(3.6, 2.4, -2.86)
  room.add(mullion)

  // подвесные лампы над столом
  ;[-0.6, 2.2].forEach((x) => {
    const cord = new Mesh(
      new CylinderGeometry(0.02, 0.02, 0.5, 8),
      new MeshStandardMaterial({ color: 0x2f363d, roughness: 0.6 })
    )
    cord.position.set(x, 3.3, 0.6)
    room.add(cord)

    const shade = new Mesh(
      new CylinderGeometry(0.42, 0.16, 0.26, 24, 1, true),
      new MeshStandardMaterial({ color: 0x30383f, roughness: 0.45, metalness: 0.35, side: DoubleSide })
    )
    shade.position.set(x, 2.95, 0.6)
    // Абажур не отбрасывает тень: открытый усечённый конус даёт на стенах
    // и корпусе холодильника кольцевой «пончик», который выглядит как артефакт.
    shade.castShadow = false
    room.add(shade)

    const bulb = new Mesh(
      new SphereGeometry(0.09, 16, 12),
      new MeshBasicMaterial({ color: 0xfff0cc })
    )
    bulb.position.set(x, 2.81, 0.6)
    room.add(bulb)
  })

  // кухонный остров: столешница на ножках
  const tableTop = roundedBox(2.1, 0.1, 1.3, 0.03, counterMaterial)
  tableTop.position.set(2.5, 0.88, 1.5)
  room.add(tableTop)

  const legMaterial = new MeshStandardMaterial({ map: woodMap, roughness: 0.68, metalness: 0.02 })
  ;[
    [1.65, 1.05],
    [3.35, 1.05],
    [1.65, 1.95],
    [3.35, 1.95],
  ].forEach(([x, z]) => {
    const leg = new Mesh(new CylinderGeometry(0.055, 0.045, 0.82, 14), legMaterial)
    leg.position.set(x, 0.41, z)
    leg.castShadow = true
    room.add(leg)
  })

  // кастрюля на столе
  const pot = new Mesh(
    new CylinderGeometry(0.2, 0.17, 0.26, 24),
    new MeshStandardMaterial({ color: 0x8e3227, roughness: 0.42, metalness: 0.2 })
  )
  pot.position.set(2.3, 1.06, 1.45)
  pot.castShadow = true
  room.add(pot)

  const potRim = new Mesh(new TorusGeometry(0.2, 0.022, 8, 24), new MeshStandardMaterial({ color: 0x2f2f2f, roughness: 0.4, metalness: 0.7 }))
  potRim.position.set(2.3, 1.19, 1.45)
  potRim.rotation.x = Math.PI / 2
  room.add(potRim)

  // разделочная доска
  const board = roundedBox(0.5, 0.035, 0.34, 0.02, new MeshStandardMaterial({ map: woodMap, roughness: 0.7 }))
  board.position.set(2.95, 0.95, 1.3)
  board.rotation.y = 0.3
  room.add(board)

  // барные стулья у острова
  const seatMaterial = new MeshStandardMaterial({ color: 0x3a3f45, roughness: 0.6, metalness: 0.1 })
  ;[1.9, 3.1].forEach((x) => {
    const seat = roundedBox(0.42, 0.09, 0.4, 0.04, seatMaterial)
    seat.position.set(x, 0.68, 2.45)
    room.add(seat)

    const back = roundedBox(0.42, 0.42, 0.07, 0.03, seatMaterial)
    back.position.set(x, 0.92, 2.62)
    back.rotation.x = -0.12
    room.add(back)

    const post = new Mesh(new CylinderGeometry(0.03, 0.03, 0.64, 10), chromeMaterial)
    post.position.set(x, 0.32, 2.45)
    room.add(post)

    const foot = new Mesh(new CylinderGeometry(0.19, 0.2, 0.03, 16), chromeMaterial)
    foot.position.set(x, 0.015, 2.45)
    room.add(foot)
  })

  // цветок в углу
  const potPlant = new Mesh(
    new CylinderGeometry(0.22, 0.17, 0.36, 20),
    new MeshStandardMaterial({ color: 0xa9714a, roughness: 0.8 })
  )
  potPlant.position.set(-3.9, 0.18, 2.3)
  potPlant.castShadow = true
  room.add(potPlant)

  const leaves = new Mesh(
    new SphereGeometry(0.34, 20, 14),
    new MeshStandardMaterial({ color: 0x40703c, roughness: 0.85 })
  )
  leaves.position.set(-3.9, 0.62, 2.3)
  leaves.scale.set(1, 1.3, 1)
  leaves.castShadow = true
  room.add(leaves)

  scene.add(room)
}

function buildFridge() {
  fridge = new Group()

  // Корпус — окрашенная сталь: металл + прозрачный лаковый слой (clearcoat).
  // Именно clearcoat даёт мягкий «живой» блик по краю панели, который
  // отличает настоящую технику от плоского мультяшного прямоугольника.
  const shellMaterial = new MeshPhysicalMaterial({
    color: 0xc6ced4,
    roughness: 0.42,
    metalness: 0.28,
    clearcoat: 0.65,
    clearcoatRoughness: 0.28,
  })
  const innerMaterial = new MeshStandardMaterial({ color: 0xd8e4ea, roughness: 0.55, metalness: 0.05 })
  const linerMaterial = new MeshStandardMaterial({ color: 0xe2ecf1, roughness: 0.68, metalness: 0.02 })
  const chromeMaterial = new MeshStandardMaterial({ color: 0xb4bac0, roughness: 0.28, metalness: 0.92 })

  // Корпус собран из панелей, а не цельной коробки: так при открытой дверце
  // видно полки и продукты, а не глухую переднюю грань.
  // Широкий корпус (2.4 м) — чтобы полки с продуктами целиком влезали
  // в широкое полотно сцены.
  const W = 2.4, H = 2.3, D = 1.25, T = 0.06

  const back = new Mesh(new BoxGeometry(W, H, T), innerMaterial)
  back.position.set(0, 0, -D / 2 + T / 2)
  back.receiveShadow = true
  back.castShadow = true
  fridge.add(back)

  const left = new Mesh(new BoxGeometry(T, H, D), shellMaterial)
  left.position.set(-W / 2 + T / 2, 0, 0)
  left.castShadow = true
  fridge.add(left)

  const right = new Mesh(new BoxGeometry(T, H, D), shellMaterial)
  right.position.set(W / 2 - T / 2, 0, 0)
  right.castShadow = true
  fridge.add(right)

  const top = new Mesh(new BoxGeometry(W, T, D), shellMaterial)
  top.position.set(0, H / 2 - T / 2, 0)
  top.castShadow = true
  fridge.add(top)

  // Потолочная панель над полками — источник «лампочки» в холодильнике.
  // Без неё верхняя полка освещена только отражённым светом и выглядит
  // заметно темнее нижней.
  const topPanel = new Mesh(
    new BoxGeometry(W - 2 * T, 0.015, 0.5),
    new MeshStandardMaterial({ color: 0xf5f5f2, roughness: 0.9, emissive: 0xfff4dc, emissiveIntensity: 0.12 })
  )
  topPanel.position.set(0, H / 2 - T - 0.03, 0.1)
  fridge.add(topPanel)

  const bottom = new Mesh(new BoxGeometry(W, T, D), shellMaterial)
  bottom.position.set(0, -H / 2 + T / 2, 0)
  bottom.castShadow = true
  fridge.add(bottom)

  // Светлая обшивка внутренних стенок — изнутри холодильник выглядит
  // белым, а не серым, как внешний корпус.
  // ВАЖНО: обшивка обязана отбрасывать тень (castShadow). Иначе солнце
  // снаружи проходит сквозь стенки и заливает внутренность — она
  // выжигается в белое пятно, и продукты теряют цвет и форму.
  ;[-1, 1].forEach((side) => {
    const liner = new Mesh(new BoxGeometry(0.02, H - 0.1, D - 0.08), linerMaterial)
    liner.position.set(side * (W / 2 - T - 0.01), 0, 0)
    liner.receiveShadow = true
    liner.castShadow = true
    fridge.add(liner)
  })

  const topLiner = new Mesh(new BoxGeometry(W - 0.1, 0.02, D - 0.08), linerMaterial)
  topLiner.position.set(0, H / 2 - T - 0.01, 0)
  topLiner.castShadow = true
  fridge.add(topLiner)

  const bottomLiner = new Mesh(new BoxGeometry(W - 0.1, 0.02, D - 0.08), linerMaterial)
  bottomLiner.position.set(0, -H / 2 + T + 0.01, 0)
  bottomLiner.castShadow = true
  fridge.add(bottomLiner)

  // полки и продукты живут в отдельных группах: продукты перестраиваются
  // при изменении списка ингредиентов, полки остаются на месте.
  shelvesGroup = new Group()
  productsGroup = new Group()
  fridge.add(shelvesGroup, productsGroup)

  // Стекло полки с лёгким голубым оттенком: на белой обшивке стенок
  // бесцветное стекло не читается, и продукты кажутся висящими в воздухе.
  const glassShelfMaterial = new MeshPhysicalMaterial({
    color: 0xa8c8d8,
    roughness: 0.16,
    metalness: 0,
    transparent: true,
    opacity: 0.6,
    clearcoat: 0.7,
    clearcoatRoughness: 0.18,
  })

  SHELF_ROWS.forEach((y) => {
    const plank = new Mesh(new BoxGeometry(W - 2 * T, 0.035, 0.86), glassShelfMaterial)
    plank.position.set(0, y, -0.2)
    plank.receiveShadow = true
    shelvesGroup.add(plank)

    // Металлический кант полки. Вынесен чуть вперёд полотна стекла —
    // иначе его грань совпадает с гранью стекла и появляется z-fighting
    // (полосатая рябь на краю полки).
    // Шероховатость повышена: при гладком металле по канту идёт узкий
    // пересвеченный блик, от которого полка выглядит «светящейся планкой».
    const edge = new Mesh(
      new BoxGeometry(W - 2 * T, 0.04, 0.04),
      new MeshStandardMaterial({ color: 0x9aa0a6, roughness: 0.52, metalness: 0.7 })
    )
    edge.position.set(0, y, 0.29)
    edge.castShadow = true
    shelvesGroup.add(edge)
  })

  // ящик для овощей внизу
  const drawer = new Mesh(
    new BoxGeometry(W - 2 * T, 0.3, 0.86),
    new MeshStandardMaterial({ color: 0xc9d8e0, roughness: 0.35, metalness: 0.08 })
  )
  drawer.position.set(0, -0.95, -0.2)
  shelvesGroup.add(drawer)

  buildProducts()

  // дверца на левой петле — со скруглёнными краями, как у настоящей техники
  door = new Group()
  const panel = roundedBox(W, H, 0.09, 0.04, shellMaterial)
  panel.castShadow = true
  door.add(panel)

  // разделение на морозильную камеру
  const groove = new Mesh(
    new BoxGeometry(W - 0.06, 0.025, 0.095),
    new MeshStandardMaterial({ color: 0x8fa7b4, roughness: 0.35, metalness: 0.6 })
  )
  groove.position.set(W / 2, 0.72, 0.004)
  door.add(groove)

  // металлические ручки
  const handle = new Mesh(new CylinderGeometry(0.028, 0.028, 0.72, 12), chromeMaterial)
  handle.position.set(W - 0.16, 0.42, 0.1)
  handle.castShadow = true
  door.add(handle)

  const handle2 = new Mesh(new CylinderGeometry(0.028, 0.028, 0.3, 12), chromeMaterial)
  handle2.position.set(W - 0.16, 0.72, 0.1)
  door.add(handle2)

  // смещение так, чтобы точка вращения совпала с левым краем дверцы
  door.position.set(-W / 2, 0, D / 2)
  panel.position.set(W / 2, 0, 0)
  fridge.add(door)

  // Свет внутри холодильника — включается, когда мы «заходим» внутрь.
  // Три лампы по высоте, чтобы полки освещались равномерно.
  // Интенсивность намеренно скромная: три лампы в белой камере при большей
  // мощности выжигают внутренность в чистое белое пятно.
  insideLights = []
  ;[0.95, 0.25, -0.45].forEach((y) => {
    const lamp = new PointLight(0xfff2d6, 0, 3.2, 2)
    lamp.position.set(0, y, 0.4)
    fridge.add(lamp)
    insideLights.push(lamp)
  })

  // холодильник стоит на полу у левой стены
  fridge.position.set(-3.2, H / 2, -0.9)
  fridge.rotation.y = 0.5
  scene.add(fridge)

  // регулируемые ножки — приподнимают корпус над полом
  ;[
    [-W / 2 + 0.14, D / 2 - 0.14],
    [W / 2 - 0.14, D / 2 - 0.14],
    [-W / 2 + 0.14, -D / 2 + 0.14],
    [W / 2 - 0.14, -D / 2 + 0.14],
  ].forEach(([x, z]) => {
    const foot = new Mesh(
      new CylinderGeometry(0.035, 0.045, 0.07, 10),
      new MeshStandardMaterial({ color: 0x3a3d40, roughness: 0.5, metalness: 0.6 })
    )
    foot.position.set(x, -H / 2 - 0.035, z)
    fridge.add(foot)
  })
}

/* ---------- камера и анимация ---------- */

/** Перестраивает продукты на полках под текущий список ингредиентов. */
function buildProducts() {
  if (!productsGroup) return

  productsGroup.traverse((object) => {
    if (object.isMesh) {
      object.geometry?.dispose()
      object.material?.dispose()
    }
  })
  productsGroup.clear()

  const layout = buildShelfLayout()
  layout.forEach((row, rowIndex) => {
    const y = SHELF_ROWS[rowIndex]

    row.forEach((style, i) => {
      const material = new MeshStandardMaterial({
        color: style.color,
        roughness: style.kind === 2 ? 0.55 : 0.42,
        metalness: 0.05,
      })
      let mesh
      let height

      if (style.kind === 1) {
        // банка / бутылка
        height = style.size * 1.6
        mesh = new Mesh(
          new CylinderGeometry(style.size * 0.42, style.size * 0.42, height, 18),
          material
        )

        // крышечка — банка без неё выглядит как обрезок трубы
        const cap = new Mesh(
          new CylinderGeometry(style.size * 0.45, style.size * 0.45, style.size * 0.16, 18),
          new MeshStandardMaterial({ color: 0xb9bcc0, roughness: 0.35, metalness: 0.85 })
        )
        cap.position.y = height / 2 + style.size * 0.08
        cap.castShadow = true
        mesh.add(cap)
      } else if (style.kind === 2) {
        // круглое — фрукт, овощ
        height = style.size * 1.15
        mesh = new Mesh(new SphereGeometry(style.size * 0.55, 20, 14), material)
      } else {
        // коробка / пачка
        height = style.size * 1.35
        mesh = new Mesh(
          new BoxGeometry(style.size, height, style.size * 0.8),
          material
        )
      }

      // Этикетка на коробке/пачке — маленькая светлая полоса спереди.
      // Без неё продукт читается как цветной кубик.
      if (style.kind === 0) {
        const label = new Mesh(
          new BoxGeometry(style.size * 0.72, height * 0.42, 0.004),
          new MeshStandardMaterial({ color: 0xf2ede2, roughness: 0.7 })
        )
        label.position.set(0, 0.02, style.size * 0.4 + 0.002)
        mesh.add(label)
      }

      const gap = 0.42
      const spread = (row.length - 1) * gap
      mesh.position.set(
        row.length > 1 ? -spread / 2 + i * gap : 0,
        y + 0.04 + height / 2,
        style.z ?? 0
      )
      mesh.castShadow = true
      productsGroup.add(mesh)
    })
  })
}

/** Локальные координаты холодильника → мировые. */
function fridgeToWorld(local) {
  fridge.updateMatrixWorld(true)
  return fridge.localToWorld(local.clone())
}

/**
 * Клик по холодильнику: приближаемся к дверце, она распахивается,
 * затем камера въезжает внутрь — к полкам.
 */
function enterFridge() {
  if (transition) return

  inside.value = true
  doorTarget = DOOR_OPEN

  const doorPos = fridgeToWorld(CAM_DOOR_LOCAL_POS)
  const doorLook = fridgeToWorld(CAM_DOOR_LOCAL_LOOK)

  // Сначала подлёт к дверце, потом — въезд внутрь.
  transition = { t: 0, doorPos }
  camPosTarget.copy(doorPos)
  camLookTarget.copy(doorLook)
  camFovTarget = 44
}

function exitFridge() {
  if (transition) return

  inside.value = false
  doorTarget = DOOR_CLOSED
  camPosTarget.copy(CAM_KITCHEN.pos)
  camLookTarget.copy(CAM_KITCHEN.look)
  camFovTarget = 46
}

/** Клик по холодильнику (в кухне) — заходим внутрь. */
function onPointerDown(event) {
  if (inside.value || !door) return

  const rect = renderer.domElement.getBoundingClientRect()
  pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1
  pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1

  raycaster.setFromCamera(pointer, camera)
  if (raycaster.intersectObject(fridge, true).length) {
    enterFridge()
  }
}

/** Подсветка холодильника при наведении — показываем, что он кликабельный. */
function onPointerMove(event) {
  if (inside.value || !fridge) return

  const rect = renderer.domElement.getBoundingClientRect()
  pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1
  pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1

  raycaster.setFromCamera(pointer, camera)
  const hit = raycaster.intersectObject(fridge, true).length > 0

  if (hit !== hoverFridge) {
    hoverFridge = hit
    renderer.domElement.style.cursor = hit ? 'pointer' : 'default'
    fridge.traverse((object) => {
      if (object.isMesh && object.material?.emissive) {
        object.material.emissive.setHex(hit ? 0x1a2830 : 0x000000)
      }
    })
  }
}

/**
 * Экранные координаты центра холодильника — тестовый хук.
 * Позволяет автотесту кликнуть точно по холодильнику, а не «на глаз».
 */
function fridgeScreenPoint() {
  if (!fridge || !camera || !renderer) return null

  const rect = renderer.domElement.getBoundingClientRect()
  const world = fridge.getWorldPosition(new Vector3())
  const projected = world.clone().project(camera)

  return {
    x: rect.left + ((projected.x + 1) / 2) * rect.width,
    y: rect.top + ((1 - projected.y) / 2) * rect.height,
  }
}

defineExpose({ enterFridge, exitFridge, fridgeScreenPoint })

// Тестовый хук: автотест (и отладка из консоли) знают, где на экране холодильник.
if (typeof window !== 'undefined') {
  window.__kitchen3d = { enterFridge, exitFridge, fridgeScreenPoint }
}

function animate(now = 0) {
  frameId = requestAnimationFrame(animate)

  const dt = lastTime ? Math.min((now - lastTime) / 1000, 0.05) : 0.016
  lastTime = now

  // дверца
  doorAngle += (doorTarget - doorAngle) * Math.min(1, dt * 6)
  if (door) {
    door.rotation.y = doorAngle
  }

  // камера плавно перетекает между двумя положениями
  const k = Math.min(1, dt * 3.2)
  camPos.lerp(camPosTarget, k)
  camLook.lerp(camLookTarget, k)
  camFov += (camFovTarget - camFov) * k

  // Когда долетели до дверцы — въезжаем внутрь.
  if (transition) {
    transition.t += dt
    if (camPos.distanceTo(transition.doorPos) < 0.4) {
      camPosTarget.copy(fridgeToWorld(CAM_INSIDE_LOCAL_POS))
      camLookTarget.copy(fridgeToWorld(CAM_INSIDE_LOCAL_LOOK))
      camFovTarget = 60
      transition = null
    }
  }

  camera.position.copy(camPos)
  camera.lookAt(camLook)
  if (Math.abs(camera.fov - camFov) > 0.01) {
    camera.fov = camFov
    camera.updateProjectionMatrix()
  }

  // свет внутри горит, только когда мы внутри
  if (insideLights.length) {
    const target = inside.value ? 1.1 : 0
    insideLights.forEach((lamp) => {
      lamp.intensity += (target - lamp.intensity) * Math.min(1, dt * 5)
    })
  }

  // лёгкое покачивание, чтобы сцена не была статичной
  if (!inside.value && fridge && !transition) {
    fridge.rotation.y = 0.5 + Math.sin(now * 0.0004) * 0.08
  }

  if (composer) {
    composer.render()
  } else {
    renderer.render(scene, camera)
  }
}

function onResize() {
  if (!renderer || !camera) return

  const { clientWidth: w, clientHeight: h } = container.value
  if (!w || !h) return

  camera.aspect = w / h
  camera.updateProjectionMatrix()
  renderer.setSize(w, h, false)

  if (composer) {
    composer.setSize(w, h)
    const pixelRatio = renderer.getPixelRatio()
    fxaaPass?.material.uniforms.resolution.value.set(1 / (w * pixelRatio), 1 / (h * pixelRatio))
  }
}

function dispose() {
  if (frameId !== null) {
    cancelAnimationFrame(frameId)
    frameId = null
  }

  resizeObserver?.disconnect()
  resizeObserver = null

  document.removeEventListener('visibilitychange', onVisibilityChange)
  renderer?.domElement.removeEventListener('pointerdown', onPointerDown)
  renderer?.domElement.removeEventListener('pointermove', onPointerMove)

  scene?.traverse((object) => {
    if (object.isMesh) {
      object.geometry?.dispose()
      if (Array.isArray(object.material)) {
        object.material.forEach((m) => m.dispose())
      } else {
        object.material?.dispose()
      }
    }
  })

  textures.forEach((t) => t.dispose())
  textures = []
  envRT?.dispose()
  pmremGenerator?.dispose()
  composer?.dispose()
  composer = null

  renderer?.dispose()
  renderer?.domElement.remove()
  renderer = null
  scene = null
  camera = null
  fridge = null
  door = null
}

function onVisibilityChange() {
  if (document.hidden) {
    if (frameId !== null) {
      cancelAnimationFrame(frameId)
      frameId = null
    }
  } else if (frameId === null && scene) {
    lastTime = 0
    animate()
  }
}

onMounted(() => {
  try {
    initScene()
  } catch (error) {
    // WebGL недоступен (старый браузер, отключённое ускорение) — показываем
    // запасной вариант вместо пустого места и ошибок в консоли.
    console.warn('3D-кухня недоступна:', error)
    supported.value = false
    return
  }

  animate()

  raycaster = new Raycaster()
  pointer = new Vector2()
  renderer.domElement.addEventListener('pointerdown', onPointerDown)
  renderer.domElement.addEventListener('pointermove', onPointerMove)

  resizeObserver = new ResizeObserver(onResize)
  resizeObserver.observe(container.value)

  document.addEventListener('visibilitychange', onVisibilityChange)
})

onBeforeUnmount(dispose)

// Список ингредиентов изменился — перестраиваем полки.
watch(
  () => props.items,
  () => buildProducts(),
  { deep: true }
)
</script>

<style scoped>
.kitchen-3d-container {
  position: relative;
  width: 100%;
  height: 460px;
  max-width: 900px;
  margin: 0 auto 20px;
  border-radius: 18px;
  overflow: hidden;
  cursor: pointer;
}

.kitchen-3d-container :deep(canvas) {
  display: block;
  width: 100% !important;
  height: 100% !important;
}

/* Виньетка — затемняет края кадра, добавляет глубины и убирает
   ощущение «мультяшной» плоской сцены. */
.vignette {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    radial-gradient(ellipse at 50% 45%, transparent 42%, rgba(20, 14, 8, .18) 78%, rgba(15, 10, 5, .42) 100%);
}

.hint {
  position: absolute;
  left: 50%;
  bottom: 14px;
  transform: translateX(-50%);
  padding: 8px 16px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  color: #6b4a2b;
  font-size: .85rem;
  box-shadow: 0 4px 14px rgba(0, 0, 0, .12);
  pointer-events: none;
  white-space: nowrap;
}

.back {
  position: absolute;
  left: 14px;
  top: 14px;
  padding: 9px 16px;
  border: none;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.95);
  color: #4a6272;
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(0, 0, 0, .14);
}

.back:hover {
  background: #fff;
}

.hint-enter-active,
.hint-leave-active {
  transition: opacity .25s ease;
}

.hint-enter-from,
.hint-leave-to {
  opacity: 0;
}

.fallback {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  height: 100%;
  padding: 20px;
  text-align: center;
  color: #6b7b87;
  background: #f7f0e6;
}

.fallback-emoji {
  font-size: 46px;
}

.fallback p {
  margin: 0;
  font-size: .9rem;
}
</style>
