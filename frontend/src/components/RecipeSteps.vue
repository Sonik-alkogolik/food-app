<template>
  <div class="steps">
    <div v-for="(step, i) in steps" :key="step.id ?? i" :id="`step-${step.step_number}`" class="step-card">
      <div class="step-num">{{ step.step_number }}</div>
      <div class="step-body">
        <div class="step-head">
          <h4>{{ step.title }}</h4>
          <span v-if="step.duration_minutes" class="duration">⏱️ {{ step.duration_minutes }} мин</span>
        </div>
        <p class="desc">{{ step.description }}</p>
        <img
          v-if="step.image_url"
          v-lazy="step.image_url"
          :alt="step.title"
          class="step-photo"
          loading="lazy"
          @click="openLightbox(step)"
          @error="$event.target.style.display = 'none'"
        />
        <button v-if="i < steps.length - 1" class="next-btn" @click="scrollToStep(steps[i + 1])">
          Следующий шаг →
        </button>
      </div>
    </div>

    <!-- Увеличение фото по клику -->
    <div v-if="lightbox" class="lightbox" @click="lightbox = null">
      <img :src="lightbox.image_url" :alt="lightbox.title" @click.stop />
      <div class="lb-caption">{{ lightbox.step_number }}. {{ lightbox.title }} (нажмите чтобы закрыть)</div>
    </div>
  </div>
</template>

<script setup>
/* ШАГ 30: пошаговое приготовление с фото, увеличение по клику, плавная прокрутка к следующему шагу */
import { ref } from 'vue'

const props = defineProps({ steps: { type: Array, default: () => [] } })
const lightbox = ref(null)

function openLightbox(step) { lightbox.value = step }

function scrollToStep(step) {
  document.getElementById(`step-${step.step_number}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<style scoped>
.steps { display: flex; flex-direction: column; gap: 16px; }
.step-card { display: flex; gap: 14px; background: #fff; border: 1px solid #eee; border-radius: 14px; padding: 16px; scroll-margin-top: 80px; }
.step-num { flex: 0 0 40px; height: 40px; border-radius: 50%; background: #ff9800; color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.step-body { flex: 1; min-width: 0; }
.step-head { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; flex-wrap: wrap; }
.step-head h4 { margin: 0; font-size: 1.05rem; }
.duration { color: #777; font-size: .85rem; white-space: nowrap; }
.desc { color: #444; line-height: 1.55; margin: 8px 0; }
.step-photo { width: 100%; max-width: 560px; border-radius: 10px; cursor: zoom-in; display: block; }
.next-btn { margin-top: 8px; background: none; border: none; color: #ff9800; font-weight: 600; cursor: pointer; padding: 4px 0; }
.lightbox { position: fixed; inset: 0; background: rgba(0,0,0,.85); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.lightbox img { max-width: 95vw; max-height: 82vh; border-radius: 8px; }
.lb-caption { color: #fff; margin-top: 12px; }
</style>
