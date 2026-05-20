<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      class="block text-sm font-medium text-slate-700"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <Popover v-slot="{ open }" class="relative block z-50">
      <PopoverButton
        type="button"
        class="input-base w-full flex items-center justify-between text-left"
        :class="[open ? 'ring-2 ring-blue-500 border-blue-500' : '']"
      >
        <span :class="displayText ? 'text-slate-800' : 'text-slate-400'">
          {{ displayText || placeholder }}
        </span>
        <CalendarDaysIcon class="h-5 w-5 shrink-0 text-slate-400" />
      </PopoverButton>

      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0"
      >
        <PopoverPanel
          v-slot="{ close }"
          class="absolute right-0 z-[9999] mt-1 w-screen max-w-sm sm:max-w-md md:max-w-lg lg:min-w-max rounded-xl border border-slate-200 bg-white p-4 shadow-xl focus:outline-none"
          @mouseleave="hoveredDay = null"
        >
          <div class="flex items-center justify-between mb-4">
            <span class="text-sm font-semibold text-slate-800">Selecione o período</span>
            <button
              v-if="tempStart || tempEnd"
              type="button"
              class="text-xs font-medium text-blue-600 hover:text-blue-700 p-1"
              @click="clearSelection"
            >
              Limpar seleção
            </button>
          </div>
          <div class="flex flex-col md:flex-row gap-6 md:gap-8">
            <!-- Mês 1 -->
            <div class="flex flex-col shrink-0">
              <div class="flex items-center justify-between mb-2">
                <button
                  type="button"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-600 transition-colors"
                  @click="prevMonth"
                >
                  <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <span class="text-sm font-bold text-slate-800">{{ monthLabel(calendarStart) }}</span>
                <button
                  type="button"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-600 transition-colors"
                  @click="nextMonth"
                >
                  <ChevronRightIcon class="h-5 w-5" />
                </button>
              </div>
              <div class="grid grid-cols-7 gap-1 text-center">
                <div
                  v-for="w in weekDays"
                  :key="w"
                  class="text-[10px] font-bold text-slate-400 py-1 uppercase"
                >
                  {{ w }}
                </div>
                <button
                  v-for="(day, idx) in calendarDays"
                  :key="idx"
                  type="button"
                  class="w-9 h-9 rounded-lg text-sm transition-all duration-200"
                  :class="dayClasses(day, calendarStart)"
                  :disabled="isDisabled(day, calendarStart)"
                  @click="onDayClick(day, close)"
                  @mouseenter="hoveredDay = day"
                >
                  {{ day.getDate() }}
                </button>
              </div>
            </div>
            <!-- Mês 2 -->
            <div class="flex flex-col shrink-0">
              <div class="flex items-center justify-center mb-2">
                <span class="text-sm font-bold text-slate-800 py-1.5">{{ monthLabel(calendarStartNext) }}</span>
              </div>
              <div class="grid grid-cols-7 gap-1 text-center">
                <div
                  v-for="w in weekDays"
                  :key="'2-' + w"
                  class="text-[10px] font-bold text-slate-400 py-1 uppercase"
                >
                  {{ w }}
                </div>
                <button
                  v-for="(day, idx) in calendarDaysNext"
                  :key="'n-' + idx"
                  type="button"
                  class="w-9 h-9 rounded-lg text-sm transition-all duration-200"
                  :class="dayClasses(day, calendarStartNext)"
                  :disabled="isDisabled(day, calendarStartNext)"
                  @click="onDayClick(day, close)"
                  @mouseenter="hoveredDay = day"
                >
                  {{ day.getDate() }}
                </button>
              </div>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
            <p class="text-[10px] text-slate-400 uppercase font-medium tracking-wider">
              {{ !tempStart ? 'Selecione a ida' : (!tempEnd ? 'Selecione a volta' : 'Período selecionado') }}
            </p>
            <div v-if="tempStart && !tempEnd" class="text-[10px] text-blue-500 font-bold uppercase">
              Aguardando retorno...
            </div>
          </div>
        </PopoverPanel>
      </transition>
    </Popover>
    <p
      v-if="error"
      class="text-xs text-red-600"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import {
  format,
  startOfMonth,
  endOfMonth,
  eachDayOfInterval,
  addMonths,
  subMonths,
  isSameDay,
  isWithinInterval,
  isBefore,
  parseISO,
  startOfWeek,
  endOfWeek,
  isSameMonth,
  startOfDay,
} from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  label: { type: String, default: '' },
  departureDate: { type: String, default: '' },
  returnDate: { type: String, default: '' },
  placeholder: { type: String, default: 'Selecione o período' },
  required: { type: Boolean, default: false },
  error: { type: String, default: '' },
  minDate: { type: [String, Date], default: null },
})

const emit = defineEmits(['update:departureDate', 'update:returnDate'])

const weekDays = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']

const calendarStart = ref(new Date())
const tempStart = ref(null)
const tempEnd = ref(null)
const hoveredDay = ref(null)

const minDateObj = computed(() => {
  if (props.minDate) {
    return startOfDay(typeof props.minDate === 'string' ? parseISO(props.minDate) : props.minDate)
  }
  return startOfDay(new Date())
})

function monthLabel(d) {
  const label = format(d, 'MMMM yyyy', { locale: ptBR })
  return label.charAt(0).toUpperCase() + label.slice(1)
}

const calendarStartNext = computed(() => addMonths(calendarStart.value, 1))

function buildCalendarDays(monthStart) {
  const start = startOfWeek(startOfMonth(monthStart), { weekStartsOn: 0 })
  const end = endOfWeek(endOfMonth(monthStart), { weekStartsOn: 0 })
  return eachDayOfInterval({ start, end })
}

const calendarDays = computed(() => buildCalendarDays(calendarStart.value))
const calendarDaysNext = computed(() => buildCalendarDays(calendarStartNext.value))

const displayText = computed(() => {
  const start = props.departureDate ? parseISO(props.departureDate) : null
  const end = props.returnDate ? parseISO(props.returnDate) : null
  if (!start || !end) return ''
  const sameYear = start.getFullYear() === end.getFullYear()
  if (sameYear) {
    return `${format(start, "d 'de' MMM", { locale: ptBR })} – ${format(end, "d 'de' MMM. yyyy", { locale: ptBR })}`
  }
  return `${format(start, "d 'de' MMM. yyyy", { locale: ptBR })} – ${format(end, "d 'de' MMM. yyyy", { locale: ptBR })}`
})

watch(
  () => [props.departureDate, props.returnDate],
  ([dep, ret]) => {
    if (dep) tempStart.value = startOfDay(parseISO(dep))
    else tempStart.value = null
    if (ret) tempEnd.value = startOfDay(parseISO(ret))
    else tempEnd.value = null
  },
  { immediate: true }
)

function isDisabled(day, month) {
  if (!isSameMonth(day, month)) return true
  const d = startOfDay(day)
  if (minDateObj.value && isBefore(d, minDateObj.value)) return true
  return false
}

function dayClasses(day, month) {
  if (!isSameMonth(day, month)) {
    return 'text-slate-200 cursor-default pointer-events-none'
  }
  
  const d = startOfDay(day)
  if (minDateObj.value && isBefore(d, minDateObj.value)) {
    return 'text-slate-300 cursor-not-allowed'
  }

  const start = tempStart.value ? startOfDay(tempStart.value) : null
  const end = tempEnd.value ? startOfDay(tempEnd.value) : null
  const hovered = hoveredDay.value ? startOfDay(hoveredDay.value) : null

  // Casos de Seleção Exata
  const isStart = start && isSameDay(d, start)
  const isEnd = end && isSameDay(d, end)

  if (isStart || isEnd) {
    return 'bg-blue-600 text-white font-bold shadow-sm z-10 scale-105'
  }

  // Range Selecionado (Ida e Volta)
  if (start && end) {
    if (isWithinInterval(d, { start, end })) {
      return 'bg-blue-50 text-blue-700 font-semibold'
    }
  }

  // Preview de Range (Apenas Ida selecionada + Hover)
  if (start && !end && hovered && !isBefore(hovered, start)) {
    if (isWithinInterval(d, { start, end: hovered })) {
      return 'bg-blue-50 text-blue-400 font-medium'
    }
  }

  return 'hover:bg-slate-100 text-slate-700 font-medium'
}

function onDayClick(day, close) {
  const d = startOfDay(day)
  if (minDateObj.value && isBefore(d, minDateObj.value)) return

  // Se nada selecionado ou ambos selecionados, inicia novo range
  if (!tempStart.value || (tempStart.value && tempEnd.value)) {
    tempStart.value = d
    tempEnd.value = null
    emit('update:departureDate', format(d, 'yyyy-MM-dd'))
    emit('update:returnDate', '')
    return
  }

  // Se já tem ida, define a volta
  if (isBefore(d, tempStart.value)) {
    // Se clicar em data anterior à ida, essa vira a nova ida
    tempStart.value = d
    emit('update:departureDate', format(d, 'yyyy-MM-dd'))
  } else {
    tempEnd.value = d
    emit('update:returnDate', format(d, 'yyyy-MM-dd'))
    if (typeof close === 'function') close()
  }
}

function clearSelection() {
  tempStart.value = null
  tempEnd.value = null
  emit('update:departureDate', '')
  emit('update:returnDate', '')
}

function prevMonth() {
  calendarStart.value = subMonths(calendarStart.value, 1)
}

function nextMonth() {
  calendarStart.value = addMonths(calendarStart.value, 1)
}
</script>
