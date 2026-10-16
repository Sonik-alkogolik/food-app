<template>
  <div ref="container" class="fridge-3d-container"></div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import * as THREE from 'three'
import { useWindowSize } from '@vueuse/core'

const container = ref(null)
let scene, camera, renderer, fridge

const { width, height } = useWindowSize()

onMounted(() => {
  initScene()
  animate()
})

function initScene() {
  // Создаем сцену
  scene = new THREE.Scene()
  scene.background = new THREE.Color(0xf0f8ff)

  // Создаем камеру
  camera = new THREE.PerspectiveCamera(
    75,
    width.value / height.value,
    0.1,
    1000
  )
  camera.position.set(0, 0, 5)

  // Создаем рендерер
  renderer = new THREE.WebGLRenderer({ antialias: true })
  renderer.setSize(width.value, height.value)
  renderer.setPixelRatio(window.devicePixelRatio)
  container.value.appendChild(renderer.domElement)

  // Добавляем освещение
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.6)
  scene.add(ambientLight)

  const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8)
  directionalLight.position.set(1, 1, 1)
  scene.add(directionalLight)

  // Создаем модель холодильника
  createFridge()
}

function createFridge() {
  // Создаем корпус холодильника
  const fridgeGeometry = new THREE.BoxGeometry(2, 1.5, 1)
  const fridgeMaterial = new THREE.MeshPhongMaterial({ color: 0x87ceeb })
  fridge = new THREE.Mesh(fridgeGeometry, fridgeMaterial)
  scene.add(fridge)

  // Создаем дверь холодильника
  const doorGeometry = new THREE.BoxGeometry(1.9, 1.5, 0.1)
  const doorMaterial = new THREE.MeshPhongMaterial({ color: 0x4682b4 })
  const door = new THREE.Mesh(doorGeometry, doorMaterial)
  door.position.set(0, 0, 0.55)
  fridge.add(door)

  // Создаем полку внутри холодильника
  const shelfGeometry = new THREE.BoxGeometry(1.9, 0.1, 0.8)
  const shelfMaterial = new THREE.MeshPhongMaterial({ color: 0xf5deb3 })
  const shelf = new THREE.Mesh(shelfGeometry, shelfMaterial)
  shelf.position.set(0, -0.5, 0)
  fridge.add(shelf)

  // Добавляем ингредиенты (простые кубики)
  const ingredients = [
    { name: 'яйца', color: 0xffd700 },
    { name: 'молоко', color: 0xffffff },
    { name: 'сыр', color: 0xffa500 },
  ]

  ingredients.forEach((ingredient, index) => {
    const geometry = new THREE.BoxGeometry(0.2, 0.2, 0.2)
    const material = new THREE.MeshPhongMaterial({ color: ingredient.color })
    const cube = new THREE.Mesh(geometry, material)

    // Располагаем ингредиенты внутри холодильника
    cube.position.set(
      -0.8 + (index * 0.4),
      -0.3,
      -0.3
    )
    fridge.add(cube)
  })
}

function animate() {
  requestAnimationFrame(animate)

  // Вращаем холодильник
  if (fridge) {
    fridge.rotation.y += 0.005
  }

  renderer.render(scene, camera)
}

// Обновление размеров при изменении окна
watch([width, height], () => {
  if (camera && renderer) {
    camera.aspect = width.value / height.value
    camera.updateProjectionMatrix()
    renderer.setSize(width.value, height.value)
  }
})

// Очистка при размонтировании
onMounted(() => {
  const handleResize = () => {
    if (camera && renderer) {
      camera.aspect = width.value / height.value
      camera.updateProjectionMatrix()
      renderer.setSize(width.value, height.value)
    }
  }

  window.addEventListener('resize', handleResize)

  return () => {
    window.removeEventListener('resize', handleResize)
  }
})
</script>

<style scoped>
.fridge-3d-container {
  width: 100%;
  height: 400px;
}
</style>