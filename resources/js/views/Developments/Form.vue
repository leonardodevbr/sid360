<template>
  <div class="space-y-6 pb-10">
    <div class="flex items-center gap-4">
      <button
        type="button"
        class="rounded-lg p-2 hover:bg-slate-100"
        @click="$router.push({ name: 'developments.index' })"
      >
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ isEdit ? 'Editar empreendimento' : 'Novo empreendimento' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize os dados e o mapa' : 'Cadastre um novo empreendimento' }}
        </p>
      </div>
    </div>

    <form v-if="!loading" class="space-y-4" @submit.prevent="submit">
      <div class="card space-y-4 p-5">
        <p class="text-sm font-semibold text-slate-700">Dados básicos</p>
        <Input v-model="form.name" label="Nome" required placeholder="Ex: Parque Empresarial Sid360" />
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Descrição</label>
          <textarea
            v-model="form.description"
            rows="3"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="Descrição do empreendimento"
          />
        </div>
        <Input v-model="form.location" label="Localização" placeholder="Endereço ou referência" />
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            v-model="form.down_payment_percent"
            label="Entrada sugerida (%)"
            type="number"
            min="0"
            max="100"
            step="0.01"
            placeholder="20"
          />
            <SelectInput
            v-model="form.status"
            label="Status"
            :options="developmentStatusFormOptions"
            :searchable="false"
          />
        </div>
        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
          <input
            v-model="form.is_featured"
            type="checkbox"
            class="h-4 w-4 rounded accent-amber-600"
          >
          <div>
            <p class="text-sm font-semibold text-amber-900">Empreendimento em destaque</p>
            <p class="text-xs text-amber-700">Exibe um badge "Em destaque" no card do site público.</p>
          </div>
        </label>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Padrão de numeração dos lotes
          </label>
          <input
            v-model="form.lot_number_pattern"
            type="text"
            placeholder="Ex: {zona}-L{numero2}"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <p class="mt-1 text-xs text-slate-400">
            Variáveis:
            <code class="rounded bg-slate-100 px-1">{zona}</code>
            <code class="rounded bg-slate-100 px-1">{numero}</code>
            <code class="rounded bg-slate-100 px-1">{numero2}</code> (2 dígitos)
            <code class="rounded bg-slate-100 px-1">{numero3}</code> (3 dígitos)
            · Ex: QA-L01, Q1L001
          </p>
        </div>
      </div>

      <div class="card space-y-4 overflow-hidden p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-slate-700">Mapa do empreendimento</p>
            <span class="text-xs text-slate-400">Desenhe o perímetro e depois as zonas</span>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-600">Cor do perímetro</span>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="color in zoneColors"
                :key="`perimeter-${color}`"
                type="button"
                class="h-6 w-6 rounded-full border-2 transition-transform"
                :style="{ background: color }"
                :class="(form.map_color || defaultPerimeterColor) === color ? 'scale-110 border-slate-800' : 'border-transparent'"
                :title="`Usar ${color}`"
                @click="form.map_color = color"
              />
            </div>
          </div>
        </div>

        <div
          ref="mapSectionRef"
          class="map-fullscreen-section map-drawing-section space-y-3 sm:space-y-4"
          :class="{ 'map-fullscreen-section--overlay': isMapFullscreen }"
        >
          <div class="map-canvas-wrap relative min-w-0">
            <div
              ref="mapContainer"
              class="map-fullscreen-canvas map-drawing-canvas h-[min(42vh,380px)] min-h-[240px] w-full overflow-hidden rounded-lg border border-slate-300 sm:h-[560px] md:h-[600px]"
            />

            <div
              v-if="mapReady"
              class="map-floating-controls"
            >
              <div class="map-floating-controls-group">
                <button
                  type="button"
                  class="map-floating-controls-btn"
                  title="Aumentar zoom"
                  aria-label="Aumentar zoom"
                  @click="zoomMapIn"
                >
                  +
                </button>
                <button
                  type="button"
                  class="map-floating-controls-btn"
                  title="Diminuir zoom"
                  aria-label="Diminuir zoom"
                  @click="zoomMapOut"
                >
                  −
                </button>
              </div>
            </div>

            <div
              v-if="zoneInvalidHint"
              class="map-zone-invalid-hint"
            >
              {{ zoneInvalidHint }}
            </div>
          </div>

          <div
            v-if="showZoneMapPicker && isEdit && !drawingMode"
            class="map-zone-picker rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
          >
            <div class="mb-2 flex items-center justify-between gap-2">
              <p class="text-xs font-semibold text-slate-700">Selecione a zona para demarcar</p>
              <button
                type="button"
                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                @click="showZoneMapPicker = false"
              >
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>

            <button
              type="button"
              class="mb-2 flex w-full items-center gap-2 rounded-lg border border-dashed border-emerald-300 bg-emerald-50 px-3 py-2 text-left text-xs font-medium text-emerald-700 hover:bg-emerald-100"
              @click="openNewZoneFromMapPicker"
            >
              <PlusIcon class="h-4 w-4 shrink-0" />
              Nova zona
            </button>

            <p v-if="!zones.length" class="px-1 py-2 text-xs text-slate-400">
              Nenhuma zona cadastrada. Crie uma nova zona para demarcar no mapa.
            </p>

            <div v-else class="max-h-48 space-y-1 overflow-y-auto">
              <button
                v-for="zone in zones"
                :key="zone.id"
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs hover:bg-slate-50"
                :class="drawingZone?.id === zone.id ? 'bg-emerald-50 ring-1 ring-emerald-200' : ''"
                @click="pickZoneForMapping(zone)"
              >
                <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: zone.color }" />
                <span class="min-w-0 flex-1">
                  <span class="block font-semibold tracking-wide text-slate-800">{{ buildZoneTitleLabel(zone) }}</span>
                  <span class="block text-slate-400">
                    {{ buildZoneMetaLabel(zone, zoneLotsCount(zone)) }}
                  </span>
                </span>
              </button>
            </div>
          </div>

          <div
            ref="mapFooterRef"
            class="map-fullscreen-footer"
            :class="{ 'map-fullscreen-footer--dedicated': isMapFullscreen }"
          >
            <div class="map-fullscreen-toolbar map-drawing-toolbar flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-2 sm:gap-y-2">
              <div class="map-toolbar-group map-toolbar-group--primary flex min-w-0 w-full flex-wrap items-center gap-2">
                <button
                  v-if="!drawingMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="startDrawPerimeter"
                >
                  <MapIcon class="h-3.5 w-3.5 shrink-0" />
                  <span class="sm:hidden">{{ form.coordinates?.length ? 'Redesenhar' : 'Desenhar' }}</span>
                  <span class="hidden sm:inline">{{ form.coordinates?.length ? 'Redesenhar perímetro' : 'Desenhar perímetro' }}</span>
                </button>
                <button
                  v-if="form.coordinates?.length && !drawingMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-red-600 hover:bg-red-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="confirmClearPerimeter"
                >
                  Limpar perímetro
                </button>
                <button
                  v-if="clearedPerimeterSnapshot && !drawingMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-amber-700 hover:bg-amber-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="undoClearPerimeter"
                >
                  <ArrowUturnLeftIcon class="h-3.5 w-3.5 shrink-0" />
                  Desfazer
                </button>
                <button
                  v-if="drawingMode && perimeterPoints.length"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-amber-600 hover:bg-amber-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="undoLastPoint"
                >
                  <ArrowUturnLeftIcon class="h-3.5 w-3.5 shrink-0" />
                  <span class="hidden sm:inline">Desfazer último ponto</span>
                  <span class="sm:hidden">Desfazer ponto</span>
                </button>
                <button
                  v-if="drawingMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-medium text-amber-700 hover:bg-amber-100 sm:justify-start sm:px-3 sm:text-xs"
                  @click="cancelDrawing"
                >
                  <XMarkIcon class="h-3.5 w-3.5 shrink-0" />
                  {{ drawingMode === 'zone' ? 'Cancelar edição' : drawingMode === 'street' ? 'Cancelar traçado' : 'Cancelar desenho' }}
                </button>
                <button
                  v-if="drawingMode && canSaveDrawing"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--save map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] disabled:cursor-not-allowed disabled:opacity-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="finishDrawing()"
                >
                  {{ drawingMode === 'street' ? 'Salvar traçado' : 'Salvar demarcação' }}
                </button>
                <button
                  v-if="!drawingMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 sm:justify-start sm:px-3 sm:text-xs"
                  :disabled="locatingUser"
                  @click="goToMyLocation"
                >
                  <MapPinIcon class="h-3.5 w-3.5 shrink-0" />
                  {{ locatingUser ? 'Localizando...' : 'Minha localização' }}
                </button>
                <span
                  v-if="drawingMode === 'perimeter'"
                  class="w-full self-center text-[11px] font-medium leading-snug text-blue-600 sm:text-xs"
                >
                  Clique no mapa para adicionar pontos. Duplo clique na bolinha remove um ponto. Com 3+ pontos, use Salvar demarcação ou clique no primeiro vértice
                  {{ perimeterPoints.length ? ` (${perimeterPoints.length} pontos)` : '' }}
                </span>
                <span
                  v-else-if="drawingMode === 'zone'"
                  class="w-full self-center text-[11px] font-medium leading-snug text-emerald-600 sm:text-xs"
                >
                  Editando {{ drawingZone?.name }} — arraste os vértices, duplo clique remove um ponto. Com pontos suficientes, use Salvar demarcação ou clique no primeiro vértice
                  {{ perimeterPoints.length ? ` (${perimeterPoints.length} pontos)` : '' }}
                </span>
                <span
                  v-else-if="drawingMode === 'street'"
                  class="w-full self-center text-[11px] font-medium leading-snug text-slate-600 sm:text-xs"
                >
                  Traçando {{ drawingStreet?.name }} — polígono fechado com no mínimo 4 pontos. Duplo clique remove um ponto
                  {{ streetDrawingHint ? ` · ${streetDrawingHint}` : '' }}
                  {{ startedFromExistingPolygon ? ' · use Salvar traçado após ajustes' : '' }}
                  {{ perimeterPoints.length ? ` (${perimeterPoints.length} pontos)` : '' }}
                </span>
              </div>

              <div class="map-toolbar-group map-toolbar-group--map grid w-full min-w-0 grid-cols-2 gap-2 sm:flex sm:w-auto sm:shrink-0 sm:flex-wrap sm:items-center sm:justify-end">
                <button
                  v-if="isEdit && !drawingMode && hasMappedZones"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn col-span-2 flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:col-span-1 sm:justify-start sm:px-3 sm:text-xs"
                  :class="visibleZoneNameTypes.length
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                    : ''"
                  @click="openZoneNamePicker"
                >
                  <TagIcon class="h-3.5 w-3.5 shrink-0" />
                  Exibir nomes
                </button>
                <button
                  v-if="isEdit && !drawingMode"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn col-span-2 flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:col-span-1 sm:justify-start sm:px-3 sm:text-xs"
                  :class="showZoneMapPicker
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                    : ''"
                  @click="toggleZoneMapPicker"
                >
                  <RectangleGroupIcon class="h-3.5 w-3.5 shrink-0" />
                  Mapear zona
                </button>
                <button
                  v-if="!drawingMode"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:justify-start sm:px-3 sm:text-xs"
                  @click="rotateMapBy(-15)"
                >
                  <span class="sm:hidden">Girar esq.</span>
                  <span class="hidden sm:inline">Girar pra esquerda</span>
                </button>
                <button
                  v-if="!drawingMode"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:justify-start sm:px-3 sm:text-xs"
                  @click="rotateMapBy(15)"
                >
                  <span class="sm:hidden">Girar dir.</span>
                  <span class="hidden sm:inline">Girar pra direita</span>
                </button>
                <button
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn col-span-2 flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:col-span-1 sm:justify-start sm:px-3 sm:text-xs"
                  @click="toggleMapFullscreen"
                >
                  <ArrowsPointingOutIcon v-if="!isMapFullscreen" class="h-3.5 w-3.5 shrink-0" />
                  <ArrowsPointingInIcon v-else class="h-3.5 w-3.5 shrink-0" />
                  {{ isMapFullscreen ? 'Sair da tela cheia' : 'Tela cheia' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isEdit" class="card space-y-4 overflow-hidden p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm font-semibold text-slate-700">
            Zonas (quadras / conjuntos / ruas)
          </p>
          <button
            type="button"
            class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-action px-3 py-1.5 text-xs font-semibold text-white hover:bg-action-hover sm:w-auto"
            @click="openZoneForm"
          >
            <PlusIcon class="h-3.5 w-3.5" />
            Nova zona
          </button>
        </div>

        <div v-if="zones.length" class="space-y-2">
          <div
            v-for="zone in zones"
            :key="zone.id"
            class="resource-list-item flex flex-col gap-3 rounded-lg border border-slate-200 p-3 sm:flex-row sm:items-center sm:gap-3 sm:py-2.5"
          >
            <div class="flex min-w-0 items-start gap-3 sm:flex-1">
              <div class="mt-1 h-3 w-3 shrink-0 rounded-full" :style="{ background: zone.color }" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold tracking-wide text-slate-800">{{ buildZoneTitleLabel(zone) }}</p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-slate-400">
                  <span class="whitespace-nowrap">{{ zoneTypeLabel(zone.type) }}</span>
                  <span v-if="zone.parent_zone_id" class="whitespace-nowrap">
                    · dentro de <strong>{{ zones.find((item) => item.id === zone.parent_zone_id)?.name }}</strong>
                  </span>
                  <span class="whitespace-nowrap">· {{ zoneLotsCount(zone) }} lote(s)</span>
                  <span v-if="zone.coordinates?.length >= 3" class="whitespace-nowrap text-emerald-600">· área definida</span>
                  <span v-else class="whitespace-nowrap text-amber-500">· sem área</span>
                </p>
              </div>
            </div>
            <div class="resource-list-actions flex w-full flex-wrap gap-1.5 sm:w-auto sm:shrink-0 sm:justify-end">
              <button
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-blue-600 hover:bg-blue-50"
                @click="startDrawZone(zone)"
              >
                {{ zone.coordinates?.length ? 'Redesenhar' : 'Desenhar área' }}
              </button>
              <button
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs"
                :class="canGenerateLotsInZone(zone)
                  ? 'text-emerald-600 hover:bg-emerald-50'
                  : 'cursor-not-allowed text-slate-300'"
                :disabled="!canGenerateLotsInZone(zone)"
                :title="generateLotsBlockedReason(zone) || undefined"
                @click="openGenerateLots(zone)"
              >
                Gerar lotes
              </button>
              <button
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-slate-500 hover:bg-slate-100"
                @click="editZone(zone)"
              >
                Editar
              </button>
              <button
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-red-500 hover:bg-red-50"
                @click="deleteZone(zone)"
              >
                Excluir
              </button>
            </div>
          </div>
        </div>
        <p v-else class="text-xs text-slate-400">Nenhuma zona cadastrada ainda.</p>
      </div>

      <div v-if="isEdit" class="card space-y-4 overflow-hidden p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm font-semibold text-slate-700">Ruas do loteamento</p>
          <button
            type="button"
            class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 sm:w-auto"
            @click="openStreetForm"
          >
            <PlusIcon class="h-3.5 w-3.5" />
            Nova rua
          </button>
        </div>

        <div v-if="streets.length" class="space-y-2">
          <div
            v-for="street in streets"
            :key="street.id"
            class="resource-list-item flex flex-col gap-3 rounded-lg border border-slate-200 p-3 sm:flex-row sm:items-center sm:gap-3 sm:py-2.5"
          >
            <div class="flex min-w-0 items-start gap-3 sm:flex-1">
              <div
                class="mt-1 h-3 w-3 shrink-0 rounded-sm"
                :style="{ background: street.color || defaultStreetColor }"
              />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-800">{{ street.name }}</p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-slate-400">
                  <span class="whitespace-nowrap">Rua</span>
                  <span v-if="hasValidStreetPolygon(street.coordinates?.length ?? 0)" class="whitespace-nowrap text-emerald-600">
                    · área definida ({{ street.coordinates.length }} pontos)
                  </span>
                  <span v-else-if="street.coordinates?.length" class="whitespace-nowrap text-amber-500">
                    · traçado incompleto (mínimo 4 pontos)
                  </span>
                  <span v-else class="whitespace-nowrap text-amber-500">· sem traçado</span>
                </p>
              </div>
            </div>
            <div class="resource-list-actions flex w-full flex-wrap gap-1.5 sm:w-auto sm:shrink-0 sm:justify-end">
              <button
                v-if="street.coordinates?.length"
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-amber-600 hover:bg-amber-50"
                @click="confirmClearStreet(street)"
              >
                Limpar traçado
              </button>
              <button
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-blue-600 hover:bg-blue-50"
                @click="startDrawStreet(street)"
              >
                {{ street.coordinates?.length ? 'Redesenhar' : 'Desenhar no mapa' }}
              </button>
              <button
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-slate-500 hover:bg-slate-100"
                @click="editStreet(street)"
              >
                Editar
              </button>
              <button
                type="button"
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-red-500 hover:bg-red-50"
                @click="deleteStreet(street)"
              >
                Excluir
              </button>
            </div>
          </div>
        </div>
        <p v-else class="text-xs text-slate-400">Nenhuma rua cadastrada ainda.</p>
      </div>

      <div v-if="isEdit" class="card space-y-3 p-5">
        <p class="text-sm font-semibold text-slate-700">
          Fotos e vídeos do empreendimento
        </p>
        <p class="text-xs text-slate-500 leading-relaxed">
          Envie imagens (capa para listagens) e vídeos em MP4 ou MOV. O <strong>primeiro vídeo</strong> da galeria (por ordem) aparece no topo da página pública do loteamento, em loop, sem som e com reprodução automática, no mesmo estilo do vídeo da página inicial do site.
        </p>
        <MediaGallery :endpoint="`/developments/${route.params.id}/media`" />
      </div>

      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="$router.push({ name: 'developments.index' })">
          Cancelar
        </Button>
        <Button type="submit" variant="primary" :disabled="saving">
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </Button>
      </div>
    </form>

    <div v-else class="card p-12 text-center text-slate-500">Carregando...</div>

    <Modal :is-open="showZoneForm" title="Zona" @close="closeZoneForm">
      <div class="space-y-3">
        <Input
          v-model="zoneForm.name"
          label="Nome da zona"
          required
          placeholder="Ex: Quadra A"
          :error="zoneFormErrors.name"
        />
        <SelectInput
          v-model="zoneForm.type"
          label="Tipo"
          :options="zoneTypeOptions"
          :searchable="false"
          :can-clear="false"
          placeholder="Selecione o tipo"
          :error="zoneFormErrors.type"
        />
        <SelectInput
          v-if="parentZoneOptions.length"
          v-model="zoneForm.parent_zone_id"
          label="Zona pai"
          :options="parentZoneOptions"
          placeholder="Nenhuma (zona independente)"
          :searchable="false"
        />
        <p v-if="parentZoneOptions.length" class="text-xs text-slate-400">
          Opcional — ex: Setor dentro de uma Quadra
        </p>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Cor no mapa</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="color in zoneColors"
              :key="color"
              type="button"
              class="h-7 w-7 rounded-full border-2 transition-transform"
              :style="{ background: color }"
              :class="zoneForm.color === color ? 'scale-110 border-slate-800' : 'border-transparent'"
              @click="zoneForm.color = color"
            />
          </div>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="closeZoneForm">Cancelar</Button>
        <Button variant="primary" :disabled="savingZone" @click="saveZone">
          {{ savingZone ? 'Salvando...' : 'Salvar zona' }}
        </Button>
      </div>
    </Modal>

    <Modal
      :is-open="showStreetForm"
      :title="editingStreet ? 'Editar rua' : 'Nova rua do loteamento'"
      @close="closeStreetForm"
    >
      <Input
        v-model="streetForm.name"
        label="Nome da rua"
        required
        placeholder="Ex: Rua Norte, Av. Principal"
      />
      <div class="mt-3">
        <label class="mb-1 block text-xs font-medium text-slate-600">Cor no mapa</label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="color in zoneColors"
            :key="`street-${color}`"
            type="button"
            class="h-7 w-7 rounded-full border-2 transition-transform"
            :style="{ background: color }"
            :class="streetForm.color === color ? 'scale-110 border-slate-800' : 'border-transparent'"
            @click="streetForm.color = color"
          />
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="closeStreetForm">Cancelar</Button>
        <Button variant="primary" :disabled="savingStreet" @click="saveStreet">
          {{ savingStreet ? 'Salvando...' : 'Salvar' }}
        </Button>
      </div>
    </Modal>

    <Modal
      :is-open="showZoneNamePicker"
      title="Exibir nomes no mapa"
      @close="closeZoneNamePicker"
    >
      <p class="text-xs text-slate-500">
        Selecione os tipos de zona cujos nomes devem aparecer no mapa.
      </p>

      <div class="mt-3 flex gap-2">
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="selectAllZoneNameTypesInDraft"
        >
          Marcar todos
        </button>
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="clearAllZoneNameTypesInDraft"
        >
          Limpar seleção
        </button>
      </div>

      <div class="mt-3 space-y-2">
        <button
          v-for="option in zoneTypeOptions"
          :key="option.value"
          type="button"
          class="flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left transition-colors"
          :class="zoneNamePickerDraft.includes(option.value)
            ? 'border-emerald-300 bg-emerald-50'
            : 'border-slate-200 bg-white hover:bg-slate-50'"
          @click="toggleZoneNameTypeDraft(option.value)"
        >
          <span>
            <span class="block text-sm font-medium text-slate-800">{{ option.label }}</span>
            <span class="block text-xs text-slate-400">
              {{ mappedZonesCountByType(option.value) }} no mapa
            </span>
          </span>
          <span
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded border"
            :class="zoneNamePickerDraft.includes(option.value)
              ? 'border-emerald-600 bg-emerald-600 text-white'
              : 'border-slate-300 bg-white text-transparent'"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
          </span>
        </button>
      </div>

      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="closeZoneNamePicker">Cancelar</Button>
        <Button variant="primary" @click="applyZoneNamePicker">Aplicar</Button>
      </div>
    </Modal>

    <Modal
      :is-open="!!generateLotsZone"
      :title="generateLotsZone ? `Gerar lotes — ${generateLotsZone.name}` : 'Gerar lotes'"
      @close="generateLotsZone = null"
    >
      <div class="space-y-3">
        <Input
          v-model="generateForm.quantity"
          label="Quantidade de lotes"
          type="number"
          min="1"
          max="500"
          required
        />
        <Input
          v-model="generateForm.start_from"
          label="Iniciar numeração em"
          type="number"
          min="1"
        />
        <Input
          v-model="generateForm.area"
          label="Área de cada lote (m²)"
          type="number"
          step="0.01"
        />
        <CurrencyInput v-model="generateForm.total_value" label="Valor de cada lote" />
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Padrão de numeração</label>
          <input
            v-model="generateForm.pattern"
            type="text"
            :placeholder="form.lot_number_pattern || '{zona}-L{numero2}'"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <p class="mt-1 text-xs text-slate-400">
            Deixe vazio para usar o padrão do empreendimento.
            Prévia: <strong>{{ previewLotNumber }}</strong>
          </p>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="generateLotsZone = null">Cancelar</Button>
        <Button variant="primary" :disabled="generating" @click="doGenerateLots">
          {{ generating ? 'Gerando...' : `Gerar ${generateForm.quantity || 0} lotes` }}
        </Button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { getApiErrorMessage } from '@/utils/apiError';
import { useAlert } from '@/composables/useAlert';
import { useMapFullscreen } from '@/composables/useMapFullscreen';
import { developmentStatusFormOptions } from '@/utils/labels';
import { setupMapBaseLayers, ensureMapRotation, configureMapRotation, refreshMapDisplay, hideMapScrollZoomHint, showMapScrollZoomHint, eventToMapLatLng } from '@/utils/mapLayers';
import {
  arePointsInsideOrOnPolygon,
  getInvalidPointsInsidePolygon,
  getPolygonEdgesMeters,
  isPointInsideOrOnPolygon,
  normalizePolygonCoordinates,
} from '@/utils/mapGeometry';
import { lotStatusLabel } from '@/utils/status';
import {
  buildZoneMetaLabel,
  buildZoneTitleLabel,
  canGenerateLotsInZone,
  generateLotsBlockedReason,
  zoneTypeLabel as zoneTypeLabelHelper,
} from '@/utils/zone';
import { createCursorPreviewController } from '@/utils/mapDrawingPreview';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import CurrencyInput from '@/components/Common/CurrencyInput.vue';
import MediaGallery from '@/components/Common/MediaGallery.vue';
import { ArrowLeftIcon, ArrowUturnLeftIcon, ArrowsPointingInIcon, ArrowsPointingOutIcon, MapIcon, MapPinIcon, PlusIcon, RectangleGroupIcon, TagIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { confirm } = useAlert();

const isEdit = computed(() => Boolean(route.params.id));
const hasMappedZones = computed(() => zones.value.some(
  (zone) => Array.isArray(zone.coordinates) && zone.coordinates.length >= 3,
));
const loading = ref(false);
const saving = ref(false);

const form = ref({
  name: '',
  description: '',
  location: '',
  status: 'active',
  is_featured: false,
  down_payment_percent: '20',
  lot_number_pattern: '{zona}-L{numero2}',
  coordinates: null,
  map_center: null,
  map_zoom: 17,
  map_color: '#1E5F8E',
});

const defaultPerimeterColor = '#1E5F8E';
const defaultStreetColor = '#64748B';
const STREET_MIN_POINTS = 4;

function getMinimumPolygonPoints(mode) {
  if (mode === 'street') {
    return STREET_MIN_POINTS;
  }

  return 3;
}

function hasValidStreetPolygon(pointCount) {
  return pointCount >= STREET_MIN_POINTS;
}

function getMinimumPointsToClose(mode) {
  return getMinimumPolygonPoints(mode);
}

const mapContainer = ref(null);
const mapSectionRef = ref(null);
const mapFooterRef = ref(null);
let map = null;
let L = null;
let perimeterLayer = null;
const perimeterPoints = ref([]);
let tempMarkers = [];
let edgeLabelMarkers = [];
let zoneLayers = {};
let streetLayersMap = {};
let lotLayersMap = {};
let locationMarker = null;
let mapLayersSetup = null;
let fullscreenResizeHandler = null;
const mapPopupActions = new WeakMap();
const drawingMode = ref(null);
const drawingZone = ref(null);
const drawingStreet = ref(null);
const locatingUser = ref(false);
const mapReady = ref(false);
const startedFromExistingPolygon = ref(false);
let firstVertexCloseTimer = null;
const cursorPreview = createCursorPreviewController();

function getDrawingStrokeColor() {
  const lineColor = drawingMode.value === 'perimeter'
    ? getPerimeterColor()
    : drawingMode.value === 'street'
      ? getStreetColor(drawingStreet.value)
      : drawingZone.value?.color ?? '#10B981';
  const zoneInvalid = drawingMode.value === 'zone'
    && getDevelopmentPerimeter()
    && getInvalidPointsInsidePolygon(perimeterPoints.value, getDevelopmentPerimeter()).length > 0;

  return zoneInvalid ? '#DC2626' : lineColor;
}

function isDrawingStrokeInvalid() {
  return drawingMode.value === 'zone'
    && getDevelopmentPerimeter()
    && getInvalidPointsInsidePolygon(perimeterPoints.value, getDevelopmentPerimeter()).length > 0;
}

function syncDrawingCursorPreview() {
  if (!map || !L || !drawingMode.value) {
    cursorPreview.unbind();
    return;
  }

  cursorPreview.bind(map, L, {
    isActive: () => Boolean(drawingMode.value) && perimeterPoints.value.length >= 1,
    getLastPoint: () => {
      const points = perimeterPoints.value;
      return points.length ? points[points.length - 1] : null;
    },
    getStrokeColor: getDrawingStrokeColor,
    getInvalid: isDrawingStrokeInvalid,
    isCursorInvalid: (latLng) => {
      if (drawingMode.value !== 'zone' || !latLng) {
        return false;
      }

      const perimeter = getDevelopmentPerimeter();
      if (!perimeter) {
        return false;
      }

      return !isPointInsideOrOnPolygon([latLng.lat, latLng.lng], perimeter);
    },
  });
}

function clearFirstVertexCloseTimer() {
  if (firstVertexCloseTimer) {
    clearTimeout(firstVertexCloseTimer);
    firstVertexCloseTimer = null;
  }
}

function scheduleCloseOnFirstVertex() {
  clearFirstVertexCloseTimer();
  firstVertexCloseTimer = setTimeout(() => {
    firstVertexCloseTimer = null;
    finishDrawing({ closedExplicitly: true });
  }, 250);
}

function syncMapContainerHeight() {
  if (!mapContainer.value || !mapSectionRef.value) return;

  if (isMapFullscreen.value) {
    const sectionStyle = window.getComputedStyle(mapSectionRef.value);
    const paddingTop = parseFloat(sectionStyle.paddingTop) || 0;
    const paddingBottom = parseFloat(sectionStyle.paddingBottom) || 0;
    const gap = 12;
    const zonePicker = mapSectionRef.value.querySelector('.map-zone-picker');
    const footerHeight = mapFooterRef.value?.offsetHeight ?? 0;
    const zonePickerHeight = zonePicker?.offsetHeight ?? 0;
    const zonePickerGap = zonePicker ? gap : 0;
    const height = window.innerHeight - paddingTop - paddingBottom - gap - footerHeight - zonePickerHeight - zonePickerGap;

    mapContainer.value.style.height = `${Math.max(Math.floor(height), 240)}px`;
    hideMapScrollZoomHint(map);
    return;
  }

  mapContainer.value.style.height = '';
  showMapScrollZoomHint(map);
}

function refreshMapLayout() {
  syncMapContainerHeight();
  refreshMapDisplay(map, mapLayersSetup ?? {});
}

const { isFullscreen: isMapFullscreen, toggleFullscreen: toggleMapFullscreen } = useMapFullscreen(
  mapSectionRef,
  refreshMapLayout,
);

const zoneInvalidHint = computed(() => {
  if (drawingMode.value !== 'zone') {
    return '';
  }

  const perimeter = getDevelopmentPerimeter();

  if (!perimeter) {
    return 'Defina o perímetro do empreendimento antes de demarcar a zona';
  }

  const invalidPoints = getInvalidPointsInsidePolygon(perimeterPoints.value, perimeter);

  if (invalidPoints.length) {
    return 'Vértice fora do perímetro — ajuste os pontos em vermelho';
  }

  if (perimeterPoints.value.length > 0 && perimeterPoints.value.length < 3) {
    return `Adicione mais ${3 - perimeterPoints.value.length} ponto(s) para fechar a zona`;
  }

  if (perimeterPoints.value.length >= 3 && !startedFromExistingPolygon.value) {
    return 'Polígono pronto — clique em Salvar demarcação ou no primeiro vértice';
  }

  return '';
});

const streetDrawingHint = computed(() => {
  if (drawingMode.value !== 'street') {
    return '';
  }

  const remaining = STREET_MIN_POINTS - perimeterPoints.value.length;

  if (remaining > 0) {
    return `faltam ${remaining} ponto(s) para atingir o mínimo de 4`;
  }

  if (!startedFromExistingPolygon.value) {
    return 'mínimo atingido — clique no primeiro vértice para fechar e salvar';
  }

  return '';
});

const canSaveDrawing = computed(() => {
  if (!drawingMode.value) {
    return false;
  }

  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);

  if (perimeterPoints.value.length < minimumPoints) {
    return false;
  }

  if (drawingMode.value === 'street' && !hasValidStreetPolygon(perimeterPoints.value.length)) {
    return false;
  }

  if (drawingMode.value === 'zone') {
    const perimeter = getDevelopmentPerimeter();

    if (
      perimeter
      && !arePointsInsideOrOnPolygon(perimeterPoints.value, perimeter)
    ) {
      return false;
    }
  }

  return true;
});

async function initMap() {
  if (!mapContainer.value) return;

  L = (await import('leaflet')).default;
  await import('leaflet/dist/leaflet.css');
  await ensureMapRotation(L);

  const center = form.value.map_center ?? [-11.4667, -39.9833];
  const zoom = form.value.map_zoom ?? 17;

  map = L.map(mapContainer.value, {
    zoomControl: false,
    scrollWheelZoom: false,
    rotate: true,
    bearing: 0,
    rotateControl: false,
  }).setView(center, zoom);

  configureMapRotation(map);

  mapLayersSetup = await setupMapBaseLayers(map, L);

  if (form.value.coordinates?.length) {
    drawPerimeterOnMap(form.value.coordinates);
  }

  map.on('click', onMapClick);
  map.on('popupopen', (e) => {
    bindPopupActionButtons(e.popup);
    window.requestAnimationFrame(() => bindPopupActionButtons(e.popup));
  });
  map.on('popupclose', (e) => {
    const popupElement = e.popup?.getElement();
    if (!popupElement) return;

    popupElement.querySelectorAll('[data-map-edit], [data-map-clear]').forEach((btn) => {
      delete btn.dataset.mapActionBound;
      L.DomEvent.off(btn);
    });
  });
  map.on('moveend zoomend', () => {
    const c = map.getCenter();
    form.value.map_center = [c.lat, c.lng];
    form.value.map_zoom = map.getZoom();
  });

  map.invalidateSize();
  mapReady.value = true;
}

function rotateMapBy(degrees) {
  if (!map?.setBearing) return;
  map.setBearing(map.getBearing() + degrees);
}

function zoomMapIn() {
  map?.zoomIn();
}

function zoomMapOut() {
  map?.zoomOut();
}

function resetMapCursor() {
  map?.getContainer()?.style.removeProperty('cursor');
}

const MAP_POPUP_OPTIONS = {
  closeButton: true,
  autoPan: true,
  keepInView: true,
};

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function blurPolygonPath(layer) {
  const path = layer?._path;
  if (!path) return;

  path.style.outline = 'none';
  path.style.boxShadow = 'none';

  if (typeof path.blur === 'function') {
    path.blur();
  }

  path.closest?.('svg')?.blur?.();
}

function bindPopupActionButton(popupElement, selector, handler) {
  if (!handler) return;

  const btn = popupElement?.querySelector(selector);
  if (!btn || btn.dataset.mapActionBound === '1') return;

  btn.dataset.mapActionBound = '1';
  L.DomEvent.off(btn);
  L.DomEvent.on(btn, 'click', (ev) => {
    L.DomEvent.stop(ev);
    map.closePopup();
    handler();
  });
}

function bindPopupActionButtons(popup) {
  const layer = popup?._source;
  const actions = layer ? mapPopupActions.get(layer) : null;
  if (!actions) return;

  const popupElement = popup.getElement();
  if (popupElement) {
    L.DomEvent.disableClickPropagation(popupElement);
    L.DomEvent.disableScrollPropagation(popupElement);
  }

  bindPopupActionButton(popupElement, '[data-map-edit]', actions.onEdit);
  bindPopupActionButton(popupElement, '[data-map-clear]', actions.onClear);
}

function bindMapFeaturePopup(layer, html, actions) {
  if (!layer || !map || !L) return;

  mapPopupActions.set(layer, actions);
  layer.bindPopup(html, MAP_POPUP_OPTIONS);

  layer.on('add', () => {
    blurPolygonPath(layer);
    layer._path?.setAttribute?.('tabindex', '-1');
  });

  layer.on('mousedown', (e) => {
    if (drawingMode.value) return;
    blurPolygonPath(layer);
    L.DomEvent.stopPropagation(e);
  });

  layer.on('click', (e) => {
    if (drawingMode.value) return;
    L.DomEvent.stopPropagation(e);
    layer.closeTooltip?.();
    blurPolygonPath(layer);
  });

  layer.on('popupclose', () => {
    blurPolygonPath(layer);
  });
}

function buildZonePopupHtml(zone) {
  return `
    <div class="map-feature-popup">
      <p class="map-feature-popup-title">${escapeHtml(buildZoneTitleLabel(zone))}</p>
      <p class="map-feature-popup-meta">
        ${escapeHtml(buildZoneMetaLabel(zone, zoneLotsCount(zone)))}
      </p>
      <div class="map-feature-popup-actions">
        <button type="button" class="map-feature-popup-btn" data-map-edit>
          Editar demarcação
        </button>
        <button type="button" class="map-feature-popup-btn map-feature-popup-btn--danger" data-map-clear>
          Limpar demarcação
        </button>
      </div>
    </div>
  `;
}

function buildPerimeterPopupHtml() {
  return `
    <div class="map-feature-popup">
      <p class="map-feature-popup-title">Perímetro do empreendimento</p>
      <p class="map-feature-popup-meta">Limite geral do empreendimento no mapa</p>
      <div class="map-feature-popup-actions">
        <button type="button" class="map-feature-popup-btn" data-map-edit>
          Editar demarcação
        </button>
        <button type="button" class="map-feature-popup-btn map-feature-popup-btn--danger" data-map-clear>
          Limpar perímetro
        </button>
      </div>
    </div>
  `;
}

function buildStreetPopupHtml(street) {
  const pointCount = street.coordinates?.length ?? 0;

  return `
    <div class="map-feature-popup">
      <p class="map-feature-popup-title">${escapeHtml(street.name)}</p>
      <p class="map-feature-popup-meta">Rua · ${pointCount} ponto(s) demarcados</p>
      <div class="map-feature-popup-actions">
        <button type="button" class="map-feature-popup-btn" data-map-edit>
          Editar traçado
        </button>
        <button type="button" class="map-feature-popup-btn map-feature-popup-btn--danger" data-map-clear>
          Limpar traçado
        </button>
      </div>
    </div>
  `;
}

const LOT_STATUS_MAP_STYLES = {
  available: { color: '#2d6a45', fill: '#3d8a5a' },
  reserved: { color: '#92400e', fill: '#f59e0b' },
  sold: { color: '#475569', fill: '#94a3b8' },
};

function getLotMapStyle(status) {
  return LOT_STATUS_MAP_STYLES[status] ?? LOT_STATUS_MAP_STYLES.available;
}

function buildLotLabel(lot) {
  const blockLabel = lot.block ? `Quadra ${lot.block}` : lot.zone?.name;
  return blockLabel ? `${blockLabel} · Lote ${lot.number}` : `Lote ${lot.number}`;
}

function buildLotPopupHtml(lot) {
  const areaLabel = lot.area_computed ?? lot.area;

  return `
    <div class="map-feature-popup">
      <p class="map-feature-popup-title">${escapeHtml(buildLotLabel(lot))}</p>
      <p class="map-feature-popup-meta">
        ${escapeHtml(lotStatusLabel(lot.status))}${areaLabel ? ` · ${Number(areaLabel).toLocaleString('pt-BR')} m²` : ''}
      </p>
      <div class="map-feature-popup-actions">
        <button type="button" class="map-feature-popup-btn" data-map-edit>
          Editar lote
        </button>
      </div>
    </div>
  `;
}

function setMapOverlaysPointerEvents(enabled) {
  map?.getContainer()?.classList.toggle('map-overlays-inactive', !enabled);
}

function resetMapFeatureLayerInteraction(layer) {
  if (layer?._path) {
    layer._path.style.pointerEvents = '';
    layer._path.style.removeProperty('pointer-events');
  }
}

function bringZoneLayersToFront() {
  Object.values(zoneLayers).forEach((layer) => {
    layer.bringToFront?.();
  });
}

function bringLotLayersToFront() {
  Object.values(lotLayersMap).forEach((layer) => {
    layer.bringToFront?.();
  });
}

function bindZoneLayerTooltip(layer, zone) {
  layer.unbindTooltip();

  if (!visibleZoneNameTypes.value.includes(zone.type)) return;

  layer.bindTooltip(buildZoneTitleLabel(zone), {
    permanent: true,
    direction: 'center',
    className: 'map-zone-name-label',
    opacity: 1,
  });
  layer.openTooltip();
}

function syncZoneNameLabels() {
  Object.entries(zoneLayers).forEach(([zoneId, layer]) => {
    const zone = zones.value.find((item) => String(item.id) === String(zoneId));
    if (!zone) return;

    bindZoneLayerTooltip(layer, zone);
  });
}

function mappedZonesCountByType(type) {
  return zones.value.filter(
    (zone) => zone.type === type && Array.isArray(zone.coordinates) && zone.coordinates.length >= 3,
  ).length;
}

function openZoneNamePicker() {
  zoneNamePickerDraft.value = [...visibleZoneNameTypes.value];
  showZoneNamePicker.value = true;
}

function closeZoneNamePicker() {
  showZoneNamePicker.value = false;
}

function toggleZoneNameTypeDraft(type) {
  const index = zoneNamePickerDraft.value.indexOf(type);
  if (index >= 0) {
    zoneNamePickerDraft.value.splice(index, 1);
    return;
  }

  zoneNamePickerDraft.value.push(type);
}

function selectAllZoneNameTypesInDraft() {
  zoneNamePickerDraft.value = zoneTypeOptions.map((option) => option.value);
}

function clearAllZoneNameTypesInDraft() {
  zoneNamePickerDraft.value = [];
}

function applyZoneNamePicker() {
  visibleZoneNameTypes.value = [...zoneNamePickerDraft.value];
  closeZoneNamePicker();
  syncZoneNameLabels();
}

function getDevelopmentPerimeter() {
  const coords = form.value.coordinates;
  return Array.isArray(coords) && coords.length >= 3 ? coords : null;
}

function canPlaceZonePoint(latLng) {
  const perimeter = getDevelopmentPerimeter();
  if (!perimeter) return true;

  return isPointInsideOrOnPolygon(latLng, perimeter);
}

function isVertexInvalid(coord) {
  return drawingMode.value === 'zone'
    && getDevelopmentPerimeter()
    && !canPlaceZonePoint(coord);
}

function getPerimeterColor() {
  return form.value.map_color || defaultPerimeterColor;
}

function getStreetColor(street) {
  return street?.color || defaultStreetColor;
}

function getDrawingBaseColor() {
  if (drawingMode.value === 'perimeter') {
    return getPerimeterColor();
  }

  if (drawingMode.value === 'street') {
    return getStreetColor(drawingStreet.value);
  }

  return drawingZone.value?.color ?? '#10B981';
}

function canDragVertexMarkers() {
  return startedFromExistingPolygon.value;
}

function isFirstVertexClosable(marker) {
  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);

  return !startedFromExistingPolygon.value
    && marker._vertexIndex === 0
    && perimeterPoints.value.length >= minimumPoints;
}

function buildVertexIcon(color, invalid = false, options = {}) {
  const { closeTarget = false, drawOnly = false } = options;

  return L.divIcon({
    className: 'map-vertex-handle-icon',
    html: `<span class="map-vertex-handle-wrap"><span class="map-vertex-handle${invalid ? ' map-vertex-handle--invalid' : ''}${closeTarget ? ' map-vertex-handle--close-target' : ''}${drawOnly ? ' map-vertex-handle--draw-only' : ''}" style="--vertex-color:${color}"></span></span>`,
    iconSize: [24, 24],
    iconAnchor: [12, 12],
  });
}

function updateVertexHandleStyle(marker) {
  if (!marker?.getElement) return;

  const coord = perimeterPoints.value[marker._vertexIndex];
  if (!coord) return;

  const invalid = isVertexInvalid(coord);
  const color = invalid ? '#DC2626' : getDrawingBaseColor();
  const handle = marker.getElement()?.querySelector('.map-vertex-handle');

  if (!handle) return;

  handle.classList.toggle('map-vertex-handle--invalid', invalid);
  handle.classList.toggle('map-vertex-handle--close-target', isFirstVertexClosable(marker));
  handle.classList.toggle('map-vertex-handle--draw-only', !canDragVertexMarkers());
  handle.style.setProperty('--vertex-color', color);
}

function getVertexIconOptions(marker) {
  return {
    closeTarget: isFirstVertexClosable(marker),
    drawOnly: !canDragVertexMarkers(),
  };
}

function refreshVertexMarkerStyles() {
  if (!L) return;

  const baseColor = getDrawingBaseColor();

  tempMarkers.forEach((marker, index) => {
    const coord = perimeterPoints.value[index];
    if (!coord) return;

    const invalid = isVertexInvalid(coord);
    const color = invalid ? '#DC2626' : baseColor;
    marker.setIcon(buildVertexIcon(color, invalid, getVertexIconOptions(marker)));
  });
}

function bringVertexMarkersToFront() {
  tempMarkers.forEach((marker) => {
    marker.bringToFront?.();
  });
}

function enableMapDraggingAfterVertexDrag() {
  if (!map) return;

  map._vertexDragActiveCount = Math.max(0, (map._vertexDragActiveCount ?? 1) - 1);
  if (map._vertexDragActiveCount === 0) {
    map.dragging.enable();
    map.scrollWheelZoom?.disable?.();
  }
}

function tryClosePolygonOnFirstVertexTap(marker) {
  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);
  if (marker._vertexIndex !== 0 || perimeterPoints.value.length < minimumPoints) {
    return false;
  }

  clearFirstVertexCloseTimer();
  finishDrawing({ closedExplicitly: true });
  return true;
}

function bindVertexMarkerDrag(marker) {
  const onMove = (moveEvent) => {
    L.DomEvent.preventDefault(moveEvent);
    marker._wasDragged = true;

    const latLng = eventToMapLatLng(map, moveEvent);
    if (!latLng) {
      return;
    }

    marker.setLatLng(latLng);
    perimeterPoints.value[marker._vertexIndex] = [latLng.lat, latLng.lng];
    refreshTempPolyline(perimeterPoints.value.length >= 3, { livePreview: true });
    updateVertexHandleStyle(marker);
  };

  const onEnd = (endEvent) => {
    L.DomEvent.preventDefault(endEvent);

    map.off('mousemove', onMove);
    map.off('touchmove', onMove);
    map.off('mouseup', onEnd);
    map.off('touchend', onEnd);
    map.off('mouseleave', onEnd);
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onEnd);
    document.removeEventListener('touchmove', onMove);
    document.removeEventListener('touchend', onEnd);

    enableMapDraggingAfterVertexDrag();

    if (!marker._wasDragged && tryClosePolygonOnFirstVertexTap(marker)) {
      return;
    }

    refreshTempPolyline(perimeterPoints.value.length >= 3);
    refreshVertexMarkerStyles();
    bringVertexMarkersToFront();
    bringEdgeLabelMarkersToFront();

    if (drawingMode.value === 'zone' && !canPlaceZonePoint(marker.getLatLng())) {
      toast.warning('Vértice fora do perímetro do empreendimento.');
    }
  };

  const onStart = (startEvent) => {
    if (!drawingMode.value) return;

    L.DomEvent.stopPropagation(startEvent);
    L.DomEvent.preventDefault(startEvent);

    if (!canDragVertexMarkers()) {
      return;
    }

    marker._wasDragged = false;

    if (!map._vertexDragActiveCount) {
      map._vertexDragActiveCount = 0;
      map.dragging.disable();
      map.scrollWheelZoom?.disable?.();
    }
    map._vertexDragActiveCount += 1;

    map.on('mousemove', onMove);
    map.on('touchmove', onMove);
    map.on('mouseup', onEnd);
    map.on('touchend', onEnd);
    map.on('mouseleave', onEnd);
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onEnd);
    document.addEventListener('touchmove', onMove, { passive: false });
    document.addEventListener('touchend', onEnd);
  };

  marker.on('mousedown', onStart);
  marker.on('touchstart', onStart);
}

function prepareMapForVertexEditing() {
  if (!map) return;

  map.touchRotate?.disable?.();

  const bearing = typeof map.getBearing === 'function' ? map.getBearing() : 0;
  if (bearing !== 0) {
    map.setBearing(0);
    refreshMapDisplay(map, mapLayersSetup ?? {});
  }
}

function restoreMapInteractionAfterDrawing() {
  if (!map) return;

  map._vertexDragActiveCount = 0;
  map.dragging.enable();
  configureMapRotation(map);
}

function addDrawingMarker(coord, color, index) {
  const invalid = isVertexInvalid(coord);
  const markerColor = invalid ? '#DC2626' : color;

  const marker = L.marker(coord, {
    draggable: false,
    autoPan: false,
    zIndexOffset: 1000,
    icon: buildVertexIcon(markerColor, invalid),
  }).addTo(map);

  marker._vertexIndex = index;
  marker.setIcon(buildVertexIcon(markerColor, invalid, getVertexIconOptions(marker)));
  bindVertexMarkerDrag(marker);

  marker.on('click', (e) => {
    L.DomEvent.stopPropagation(e);
    tryClosePolygonOnFirstVertexTap(marker);
  });

  marker.on('touchend', (e) => {
    if (canDragVertexMarkers()) return;

    L.DomEvent.stopPropagation(e);
    tryClosePolygonOnFirstVertexTap(marker);
  });

  marker.on('dblclick', (e) => {
    L.DomEvent.stopPropagation(e);
    L.DomEvent.preventDefault(e);
    clearFirstVertexCloseTimer();
    removeVertexAtIndex(marker._vertexIndex);
  });

  marker.on('mousedown', (e) => {
    L.DomEvent.stopPropagation(e);
  });

  tempMarkers.push(marker);
}

function preloadDrawingPoints(coords, color) {
  clearTempLayers();
  perimeterPoints.value = coords.map((c) => [Number(c[0]), Number(c[1])]);
  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);
  startedFromExistingPolygon.value = perimeterPoints.value.length >= minimumPoints;

  perimeterPoints.value.forEach((coord, index) => addDrawingMarker(coord, color, index));
  refreshTempPolyline(perimeterPoints.value.length >= 3);
  syncDrawingCursorPreview();
}

function removeVertexAtIndex(index) {
  if (!drawingMode.value || index < 0 || index >= perimeterPoints.value.length) {
    return;
  }

  const minPoints = 1;
  if (perimeterPoints.value.length <= minPoints) {
    toast.warning('Não é possível remover este ponto.');
    return;
  }

  perimeterPoints.value.splice(index, 1);

  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);
  if (perimeterPoints.value.length < minimumPoints) {
    startedFromExistingPolygon.value = false;
  }

  tempMarkers.forEach((marker) => map?.removeLayer(marker));
  tempMarkers = [];

  if (map?._tempLine) {
    map.removeLayer(map._tempLine);
    delete map._tempLine;
  }

  const color = getDrawingBaseColor();
  perimeterPoints.value.forEach((coord, pointIndex) => {
    addDrawingMarker(coord, color, pointIndex);
  });

  if (perimeterPoints.value.length >= 2) {
    refreshTempPolyline(perimeterPoints.value.length >= 3);
  } else {
    clearEdgeLabelMarkers();
  }

  toast.info('Ponto removido.');
}

function undoLastPoint() {
  if (!perimeterPoints.value.length) return;

  perimeterPoints.value.pop();
  const minimumPoints = drawingMode.value
    ? getMinimumPolygonPoints(drawingMode.value)
    : 3;
  if (perimeterPoints.value.length < minimumPoints) {
    startedFromExistingPolygon.value = false;
  }

  const marker = tempMarkers.pop();
  if (marker) {
    map?.removeLayer(marker);
  }

  if (map?._tempLine) {
    map.removeLayer(map._tempLine);
    delete map._tempLine;
  }

  if (perimeterPoints.value.length >= 2) {
    refreshTempPolyline(perimeterPoints.value.length >= 3);
  }
}

function onMapClick(e) {
  if (!drawingMode.value || !L) return;

  const { lat, lng } = e.latlng;
  const minPointsToClose = getMinimumPointsToClose(drawingMode.value);

  perimeterPoints.value.push([lat, lng]);

  const markerColor = drawingMode.value === 'perimeter'
    ? getPerimeterColor()
    : drawingMode.value === 'street'
      ? getStreetColor(drawingStreet.value)
      : drawingZone.value?.color ?? '#10B981';

  addDrawingMarker([lat, lng], markerColor, perimeterPoints.value.length - 1);

  if (
    drawingMode.value !== 'street'
    && perimeterPoints.value.length >= minPointsToClose
    && isNearFirst(e.latlng)
  ) {
    finishDrawing({ closedExplicitly: true });
    return;
  }

  refreshTempPolyline(false);
  refreshVertexMarkerStyles();
  syncDrawingCursorPreview();

  if (drawingMode.value === 'zone' && !canPlaceZonePoint([lat, lng])) {
    toast.warning('Vértice fora do perímetro do empreendimento.');
  }
}

function isNearFirst(latlng) {
  if (perimeterPoints.value.length < 3 || !L) return false;
  const first = L.latLng(perimeterPoints.value[0][0], perimeterPoints.value[0][1]);
  return latlng.distanceTo(first) < 15;
}

function clearEdgeLabelMarkers() {
  edgeLabelMarkers.forEach((marker) => map?.removeLayer(marker));
  edgeLabelMarkers = [];
}

function refreshEdgeLabels() {
  clearEdgeLabelMarkers();

  if (!L || !map || !drawingMode.value || perimeterPoints.value.length < 2) {
    return;
  }

  const isPolygonDrawing = drawingMode.value === 'street'
    ? false
    : perimeterPoints.value.length >= 3;
  const edges = getPolygonEdgesMeters(perimeterPoints.value, {
    closed: isPolygonDrawing,
    includeClosingPreview: false,
  });

  const zoneInvalid = drawingMode.value === 'zone'
    && getDevelopmentPerimeter()
    && getInvalidPointsInsidePolygon(perimeterPoints.value, getDevelopmentPerimeter()).length > 0;

  edges.forEach((edge) => {
    const marker = L.marker(edge.midpoint, {
      interactive: false,
      keyboard: false,
      zIndexOffset: 1200,
      icon: L.divIcon({
        className: 'map-edge-label-icon',
        html: `<span class="map-edge-label${edge.isClosingPreview ? ' map-edge-label--closing' : ''}${edge.isShortEdge ? ' map-edge-label--short' : ''}${zoneInvalid ? ' map-edge-label--invalid' : ''}">${edge.lengthLabel}</span>`,
        iconSize: [0, 0],
      }),
    }).addTo(map);

    edgeLabelMarkers.push(marker);
  });
}

function bringEdgeLabelMarkersToFront() {
  edgeLabelMarkers.forEach((marker) => marker.bringToFront?.());
}

function refreshTempPolyline(closed = false, options = {}) {
  const { livePreview = false } = options;

  if (!L || perimeterPoints.value.length < 2) return;
  if (map._tempLine) map.removeLayer(map._tempLine);
  if (map._tempClosingLine) {
    map.removeLayer(map._tempClosingLine);
    delete map._tempClosingLine;
  }

  const lineColor = drawingMode.value === 'perimeter'
    ? getPerimeterColor()
    : drawingMode.value === 'street'
      ? getStreetColor(drawingStreet.value)
      : drawingZone.value?.color ?? '#10B981';
  const zoneInvalid = drawingMode.value === 'zone'
    && getDevelopmentPerimeter()
    && getInvalidPointsInsidePolygon(perimeterPoints.value, getDevelopmentPerimeter()).length > 0;
  const strokeColor = zoneInvalid ? '#DC2626' : lineColor;

  const layerOptions = {
    color: strokeColor,
    weight: drawingMode.value === 'street' ? 2 : 2,
    dashArray: drawingMode.value === 'street' ? undefined : '4',
    interactive: false,
  };

  const shouldRenderClosed = drawingMode.value === 'street'
    ? false
    : closed && perimeterPoints.value.length >= 3;

  if (shouldRenderClosed) {
    map._tempLine = L.polygon(perimeterPoints.value, {
      ...layerOptions,
      fillColor: strokeColor,
      fillOpacity: drawingMode.value === 'street' ? 0.15 : 0.12,
    }).addTo(map);
  } else {
    map._tempLine = L.polyline(perimeterPoints.value, layerOptions).addTo(map);
  }

  if (livePreview) {
    return;
  }

  refreshEdgeLabels();
  refreshVertexMarkerStyles();
  bringVertexMarkersToFront();
  bringEdgeLabelMarkersToFront();
}

async function finishDrawing({ closedExplicitly = false } = {}) {
  if (drawingMode.value === 'perimeter' && perimeterPoints.value.length < 3) {
    toast.warning('O perímetro precisa de pelo menos 3 pontos.');
    return;
  }

  if (drawingMode.value === 'zone' && perimeterPoints.value.length < 3) {
    toast.warning('A zona precisa de pelo menos 3 pontos.');
    return;
  }

  if (drawingMode.value === 'street' && !hasValidStreetPolygon(perimeterPoints.value.length)) {
    toast.warning('A rua precisa de no mínimo 4 pontos.');
    return;
  }

  if (
    drawingMode.value === 'zone'
    && getDevelopmentPerimeter()
    && !arePointsInsideOrOnPolygon(perimeterPoints.value, getDevelopmentPerimeter())
  ) {
    toast.error('Todos os pontos da zona devem ficar dentro do perímetro do empreendimento.');
    return;
  }

  const mode = drawingMode.value;
  const savedZone = drawingZone.value;
  const savedStreet = drawingStreet.value;
  const savedCoords = [...perimeterPoints.value];

  cursorPreview.unbind();
  clearTempLayers();
  resetMapCursor();
  perimeterPoints.value = [];
  startedFromExistingPolygon.value = false;
  drawingMode.value = null;
  drawingZone.value = null;
  drawingStreet.value = null;
  setMapOverlaysPointerEvents(true);
  restoreMapInteractionAfterDrawing();

  if (mode === 'perimeter') {
    clearedPerimeterSnapshot.value = null;
    form.value.coordinates = savedCoords;
    drawPerimeterOnMap(form.value.coordinates);
    drawZonesOnMap();
    await persistPerimeterCoordinates(savedCoords);
    return;
  }

  if (mode === 'zone' && savedZone) {
    const zoneIndex = zones.value.findIndex((zone) => zone.id === savedZone.id);
    if (zoneIndex >= 0) {
      zones.value[zoneIndex] = {
        ...zones.value[zoneIndex],
        coordinates: savedCoords,
      };
    }

    drawZonesOnMap();
    saveZoneCoordinates(savedZone, savedCoords);
    return;
  }

  if (mode === 'street' && savedStreet) {
    saveStreetCoordinates(savedStreet, savedCoords);
  }
}

function clearTempLayers() {
  tempMarkers.forEach((m) => map?.removeLayer(m));
  tempMarkers = [];
  clearEdgeLabelMarkers();
  if (map?._tempLine) {
    map.removeLayer(map._tempLine);
    delete map._tempLine;
  }
  if (map?._tempClosingLine) {
    map.removeLayer(map._tempClosingLine);
    delete map._tempClosingLine;
  }

  cursorPreview.clear();
}

function drawPerimeterOnMap(coords) {
  if (!L || !map) return;
  if (perimeterLayer) map.removeLayer(perimeterLayer);

  const color = getPerimeterColor();

  perimeterLayer = L.polygon(coords, {
    color,
    weight: 2.5,
    fillColor: color,
    fillOpacity: 0.08,
    className: 'map-feature-polygon',
  }).addTo(map);

  resetMapFeatureLayerInteraction(perimeterLayer);

  bindMapFeaturePopup(
    perimeterLayer,
    buildPerimeterPopupHtml(),
    {
      onEdit: () => startDrawPerimeter(),
      onClear: () => confirmClearPerimeter(),
    },
  );

  bringZoneLayersToFront();
  map.fitBounds(perimeterLayer.getBounds(), { padding: [20, 20] });
}

function drawZonesOnMap() {
  if (!L || !map) return;

  Object.values(zoneLayers).forEach((layer) => map.removeLayer(layer));
  zoneLayers = {};

  zones.value.forEach((zone) => {
    if (!zone.coordinates?.length) return;

    const layer = L.polygon(zone.coordinates, {
      color: zone.color,
      weight: 2,
      fillColor: zone.color,
      fillOpacity: 0.15,
      className: 'map-feature-polygon',
    }).addTo(map);

    bindZoneLayerTooltip(layer, zone);

    resetMapFeatureLayerInteraction(layer);
    layer.bringToFront();

    bindMapFeaturePopup(
      layer,
      buildZonePopupHtml(zone),
      {
        onEdit: () => startDrawZone(zone),
        onClear: () => confirmClearZone(zone),
      },
    );

    zoneLayers[zone.id] = layer;
  });

  bringZoneLayersToFront();
  bringLotLayersToFront();
}

function startDrawPerimeter() {
  if (drawingMode.value === 'zone' || drawingMode.value === 'street') {
    cancelDrawing();
  }

  clearTempLayers();
  prepareMapForVertexEditing();
  setMapOverlaysPointerEvents(false);
  drawingMode.value = 'perimeter';
  drawingZone.value = null;
  showZoneMapPicker.value = false;

  if (form.value.coordinates?.length >= 3) {
    if (perimeterLayer) {
      map?.removeLayer(perimeterLayer);
      perimeterLayer = null;
    }
    preloadDrawingPoints(form.value.coordinates, getPerimeterColor());
    toast.info('Perímetro carregado. Arraste os vértices ou adicione novos pontos no mapa.');
  } else {
    perimeterPoints.value = [];
    startedFromExistingPolygon.value = false;
  }

  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  syncDrawingCursorPreview();
}

function startDrawZone(zone) {
  if (drawingMode.value === 'perimeter' || drawingMode.value === 'street') {
    cancelDrawing();
  }

  clearTempLayers();
  prepareMapForVertexEditing();
  setMapOverlaysPointerEvents(false);
  drawingMode.value = 'zone';
  drawingZone.value = zone;
  showZoneMapPicker.value = false;

  if (zone.coordinates?.length >= 3) {
    if (zone.id && zoneLayers[zone.id]) {
      map?.removeLayer(zoneLayers[zone.id]);
      delete zoneLayers[zone.id];
    }
    preloadDrawingPoints(zone.coordinates, zone.color ?? '#10B981');
    toast.info(`Área de "${zone.name}" carregada. Arraste os vértices ou adicione novos pontos no mapa.`);
  } else {
    perimeterPoints.value = [];
    startedFromExistingPolygon.value = false;
  }

  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  syncDrawingCursorPreview();
}

function toggleZoneMapPicker() {
  if (!isEdit.value) return;

  showZoneMapPicker.value = !showZoneMapPicker.value;
}

function pickZoneForMapping(zone) {
  if (drawingMode.value === 'perimeter') {
    cancelDrawing();
  }

  startDrawZone(zone);

  if (!zone.coordinates?.length || zone.coordinates.length < 3) {
    toast.info(`Desenhando área de "${zone.name}". Clique no mapa para marcar os vértices.`);
  }
}

function openNewZoneFromMapPicker() {
  showZoneMapPicker.value = false;
  openZoneForm();
}

function cancelDrawing() {
  clearFirstVertexCloseTimer();
  cursorPreview.unbind();
  clearTempLayers();
  resetMapCursor();
  perimeterPoints.value = [];
  startedFromExistingPolygon.value = false;
  drawingMode.value = null;
  drawingZone.value = null;
  drawingStreet.value = null;
  showZoneMapPicker.value = false;
  setMapOverlaysPointerEvents(true);
  restoreMapInteractionAfterDrawing();

  if (form.value.coordinates?.length) {
    drawPerimeterOnMap(form.value.coordinates);
  }

  drawZonesOnMap();
  drawStreetsOnMap();
  drawLotsOnMap();
}

function goToMyLocation() {
  if (!navigator.geolocation) {
    toast.error('GPS não disponível neste dispositivo.');
    return;
  }

  locatingUser.value = true;

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const coords = [pos.coords.latitude, pos.coords.longitude];

      if (map && L) {
        map.setView(coords, Math.max(map.getZoom(), 17));

        if (locationMarker) {
          map.removeLayer(locationMarker);
        }

        locationMarker = L.circleMarker(coords, {
          radius: 8,
          color: '#2563EB',
          fillColor: '#3B82F6',
          fillOpacity: 0.85,
          weight: 2,
        }).addTo(map);
      }

      locatingUser.value = false;
    },
    (err) => {
      toast.error(`Erro ao obter localização: ${err.message}`);
      locatingUser.value = false;
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  );
}

const clearedPerimeterSnapshot = ref(null);

async function persistPerimeterCoordinates(coords, { successMessage = 'Perímetro salvo.' } = {}) {
  if (!isEdit.value) {
    toast.success('Demarcação aplicada. Clique em Salvar no formulário para gravar.');
    return;
  }

  try {
    await api.post(`/developments/${route.params.id}/update`, {
      coordinates: coords,
      map_center: form.value.map_center ?? null,
      map_zoom: form.value.map_zoom ?? null,
    });
    toast.success(successMessage);
  } catch {
    toast.error('Erro ao salvar perímetro.');
  }
}

async function confirmClearPerimeter() {
  const ok = await confirm(
    'Limpar perímetro',
    'O desenho do perímetro será removido do mapa. Você poderá desfazer esta ação em seguida.',
    'Sim, limpar',
  );
  if (!ok) return;

  await applyClearPerimeter();
}

async function applyClearPerimeter() {
  const coords = form.value.coordinates;
  if (Array.isArray(coords) && coords.length >= 3) {
    clearedPerimeterSnapshot.value = coords.map((point) => [Number(point[0]), Number(point[1])]);
  }

  form.value.coordinates = null;
  if (perimeterLayer) {
    map?.removeLayer(perimeterLayer);
    perimeterLayer = null;
  }

  if (isEdit.value) {
    await persistPerimeterCoordinates(null, { successMessage: 'Perímetro removido.' });
  }
}

async function undoClearPerimeter() {
  const snapshot = clearedPerimeterSnapshot.value;
  if (!Array.isArray(snapshot) || snapshot.length < 3) return;

  form.value.coordinates = snapshot.map((point) => [Number(point[0]), Number(point[1])]);
  clearedPerimeterSnapshot.value = null;
  drawPerimeterOnMap(form.value.coordinates);
  await persistPerimeterCoordinates(form.value.coordinates, { successMessage: 'Perímetro restaurado.' });
}

async function saveZoneCoordinates(zone, coords) {
  try {
    await api.post(`/developments/${route.params.id}/zones/${zone.id}/update`, {
      name: zone.name,
      type: zone.type,
      color: zone.color,
      order: zone.order,
      parent_zone_id: zone.parent_zone_id ?? null,
      coordinates: coords,
    });
    toast.success('Área da zona salva.');
    await loadZones();
    setMapOverlaysPointerEvents(true);
    drawZonesOnMap();
  } catch {
    toast.error('Erro ao salvar área da zona.');
    await loadZones();
    setMapOverlaysPointerEvents(true);
    drawZonesOnMap();
  }
}

async function confirmClearZone(zone) {
  const ok = await confirm(
    'Limpar demarcação',
    `A área demarcada de "${zone.name}" será removida do mapa.`,
    'Sim, limpar',
  );
  if (!ok) return;

  try {
    await api.post(`/developments/${route.params.id}/zones/${zone.id}/update`, {
      name: zone.name,
      type: zone.type,
      color: zone.color,
      order: zone.order,
      parent_zone_id: zone.parent_zone_id ?? null,
      coordinates: null,
    });
    toast.success('Demarcação da zona removida.');
    await loadZones();
    drawZonesOnMap();
  } catch {
    toast.error('Erro ao limpar demarcação da zona.');
  }
}

const zones = ref([]);
const streets = ref([]);
const lots = ref([]);
const visibleZoneNameTypes = ref([]);
const showZoneNamePicker = ref(false);
const zoneNamePickerDraft = ref([]);
const showZoneForm = ref(false);
const showStreetForm = ref(false);
const showZoneMapPicker = ref(false);
const savingZone = ref(false);
const savingStreet = ref(false);
const editingZone = ref(null);
const editingStreet = ref(null);

const zoneColors = [
  '#3B82F6',
  '#0EA5E9',
  '#06B6D4',
  '#14B8A6',
  '#10B981',
  '#059669',
  '#84CC16',
  '#65A30D',
  '#EAB308',
  '#F59E0B',
  '#F97316',
  '#EF4444',
  '#F43F5E',
  '#EC4899',
  '#D946EF',
  '#A855F7',
  '#8B5CF6',
  '#6366F1',
  '#64748B',
  '#78716C',
];

const zoneTypeOptions = [
  { value: 'quadra', label: 'Quadra' },
  { value: 'conjunto', label: 'Conjunto' },
  { value: 'setor', label: 'Setor' },
  { value: 'rua', label: 'Rua' },
  { value: 'outro', label: 'Outro' },
];

const zoneForm = reactive({ name: '', type: 'quadra', color: '#3B82F6', parent_zone_id: '' });
const zoneFormErrors = reactive({ name: '', type: '' });
const streetForm = ref({ name: '', color: defaultStreetColor });

const parentZoneOptions = computed(() =>
  zones.value
    .filter((zone) =>
      ['quadra', 'conjunto'].includes(zone.type)
      && !zone.parent_zone_id
      && zone.id !== editingZone.value?.id,
    )
    .map((zone) => ({
      value: String(zone.id),
      label: `${buildZoneTitleLabel(zone)} (${zoneTypeLabel(zone.type)})`,
    })),
);

function zoneTypeLabel(type) {
  return zoneTypeLabelHelper(type);
}

function zoneLotsCount(zone) {
  return zone.lots_count ?? zone.lots?.length ?? 0;
}

async function loadZones() {
  if (!route.params.id) return;

  try {
    const { data } = await api.get(`/developments/${route.params.id}/zones`);
    zones.value = Array.isArray(data) ? data : data.data ?? [];
  } catch {
    zones.value = [];
  }
}

async function loadStreets() {
  if (!route.params.id) return;

  try {
    const { data } = await api.get(`/developments/${route.params.id}/streets`);
    streets.value = Array.isArray(data) ? data : data.data ?? [];
  } catch {
    streets.value = [];
  }
}

async function loadLots() {
  if (!route.params.id) return;

  try {
    const { data } = await api.get(`/developments/${route.params.id}/lots`, {
      params: { all: 1 },
    });
    const items = Array.isArray(data) ? data : data.data ?? [];

    lots.value = items.map((lot) => ({
      ...lot,
      coordinates: normalizePolygonCoordinates(lot.coordinates),
    }));
  } catch {
    lots.value = [];
  }
}

function drawLotsOnMap() {
  if (!L || !map) return;

  Object.values(lotLayersMap).forEach((layer) => map.removeLayer(layer));
  lotLayersMap = {};

  lots.value.forEach((lot) => {
    const coords = normalizePolygonCoordinates(lot.coordinates);
    if (!coords || coords.length < 3) return;

    const style = getLotMapStyle(lot.status);
    const layer = L.polygon(coords, {
      color: style.color,
      weight: 2,
      fillColor: style.fill,
      fillOpacity: 0.35,
      className: 'map-feature-polygon map-lot-context-path',
    }).addTo(map);

    layer.bindTooltip(buildLotLabel(lot), {
      sticky: true,
      direction: 'center',
      className: 'map-lot-context-label',
    });

    resetMapFeatureLayerInteraction(layer);

    bindMapFeaturePopup(
      layer,
      buildLotPopupHtml(lot),
      {
        onEdit: () => router.push({ name: 'lots.edit', params: { id: lot.id } }),
      },
    );

    lotLayersMap[lot.id] = layer;
  });

  bringLotLayersToFront();
}

function drawStreetsOnMap() {
  if (!L || !map) return;

  Object.values(streetLayersMap).forEach((layer) => map.removeLayer(layer));
  streetLayersMap = {};

  streets.value.forEach((street) => {
    if (!hasValidStreetPolygon(street.coordinates?.length ?? 0)) return;

    const color = getStreetColor(street);
    const layer = L.polygon(street.coordinates, {
      color,
      weight: 2,
      fillColor: color,
      fillOpacity: 0.15,
      className: 'map-feature-polygon',
    })
      .bindTooltip(street.name, { sticky: true })
      .addTo(map);

    resetMapFeatureLayerInteraction(layer);

    bindMapFeaturePopup(
      layer,
      buildStreetPopupHtml(street),
      {
        onEdit: () => startDrawStreet(street),
        onClear: () => confirmClearStreet(street),
      },
    );

    streetLayersMap[street.id] = layer;
  });
}

function openStreetForm() {
  editingStreet.value = null;
  streetForm.value = { name: '', color: defaultStreetColor };
  showStreetForm.value = true;
}

function editStreet(street) {
  editingStreet.value = street;
  streetForm.value = {
    name: street.name,
    color: street.color || defaultStreetColor,
  };
  showStreetForm.value = true;
}

function closeStreetForm() {
  showStreetForm.value = false;
  editingStreet.value = null;
  streetForm.value = { name: '', color: defaultStreetColor };
}

async function saveStreet() {
  if (!streetForm.value.name.trim()) {
    toast.warning('Informe o nome da rua.');
    return;
  }

  savingStreet.value = true;

  try {
    if (editingStreet.value) {
      await api.post(
        `/developments/${route.params.id}/streets/${editingStreet.value.id}/update`,
        streetForm.value,
      );
      toast.success('Rua atualizada.');
    } else {
      await api.post(`/developments/${route.params.id}/streets`, streetForm.value);
      toast.success('Rua criada.');
    }

    closeStreetForm();
    await loadStreets();
    drawStreetsOnMap();
  } catch {
    toast.error('Erro ao salvar rua.');
  } finally {
    savingStreet.value = false;
  }
}

async function deleteStreet(street) {
  const ok = await confirm(
    'Excluir rua',
    `Excluir "${street.name}"?`,
    'Sim, excluir',
  );
  if (!ok) return;

  try {
    await api.post(`/developments/${route.params.id}/streets/${street.id}/delete`);
    toast.success('Rua excluída.');
    await loadStreets();

    if (streetLayersMap[street.id]) {
      map?.removeLayer(streetLayersMap[street.id]);
      delete streetLayersMap[street.id];
    }
  } catch {
    toast.error('Erro ao excluir rua.');
  }
}

async function confirmClearStreet(street) {
  const ok = await confirm(
    'Limpar traçado',
    `O traçado de "${street.name}" será removido do mapa. A rua continuará cadastrada.`,
    'Sim, limpar',
  );
  if (!ok) return;

  if (drawingMode.value === 'street' && drawingStreet.value?.id === street.id) {
    cancelDrawing();
  }

  try {
    await api.post(`/developments/${route.params.id}/streets/${street.id}/update`, {
      name: street.name,
      color: street.color,
      order: street.order,
      coordinates: null,
    });
    toast.success('Traçado da rua removido.');
    await loadStreets();
    drawStreetsOnMap();
  } catch {
    toast.error('Erro ao limpar traçado da rua.');
  }
}

function startDrawStreet(street) {
  if (drawingMode.value === 'perimeter' || drawingMode.value === 'zone') {
    cancelDrawing();
  }

  clearTempLayers();
  prepareMapForVertexEditing();
  setMapOverlaysPointerEvents(false);
  drawingMode.value = 'street';
  drawingStreet.value = street;
  drawingZone.value = null;
  showZoneMapPicker.value = false;

  if (hasValidStreetPolygon(street.coordinates?.length ?? 0)) {
    if (streetLayersMap[street.id]) {
      map?.removeLayer(streetLayersMap[street.id]);
      delete streetLayersMap[street.id];
    }
    preloadDrawingPoints(street.coordinates, getStreetColor(street));
    toast.info(`Área de "${street.name}" carregada. Arraste os vértices ou feche novamente para salvar.`);
  } else {
    perimeterPoints.value = [];
    startedFromExistingPolygon.value = false;
    toast.info(`Marque no mínimo 4 pontos para demarcar "${street.name}".`);
  }

  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  syncDrawingCursorPreview();
}

async function saveStreetCoordinates(street, coords) {
  try {
    await api.post(`/developments/${route.params.id}/streets/${street.id}/update`, {
      name: street.name,
      color: street.color,
      order: street.order != null ? Number(street.order) : null,
      coordinates: coords,
    });
    toast.success('Traçado da rua salvo.');
    await loadStreets();
    drawStreetsOnMap();
  } catch (err) {
    const status = err?.response?.status;
    if (status === 403) {
      toast.error('Sem permissão para editar ruas. Verifique se seu usuário tem acesso de edição ao empreendimento.');
    } else {
      toast.error(err?.response?.data?.message ?? 'Erro ao salvar traçado da rua.');
    }
    await loadStreets();
    drawStreetsOnMap();
  }
}

function clearZoneFormErrors() {
  zoneFormErrors.name = '';
  zoneFormErrors.type = '';
}

function resetZoneForm() {
  zoneForm.name = '';
  zoneForm.type = 'quadra';
  zoneForm.color = '#3B82F6';
  zoneForm.parent_zone_id = '';
  clearZoneFormErrors();
}

function openZoneForm() {
  editingZone.value = null;
  resetZoneForm();
  showZoneForm.value = true;
}

function editZone(zone) {
  editingZone.value = zone;
  zoneForm.name = zone.name ?? '';
  zoneForm.type = zone.type ?? 'quadra';
  zoneForm.color = zone.color ?? '#3B82F6';
  zoneForm.parent_zone_id = zone.parent_zone_id ? String(zone.parent_zone_id) : '';
  clearZoneFormErrors();
  showZoneForm.value = true;
}

function closeZoneForm() {
  showZoneForm.value = false;
  editingZone.value = null;
  resetZoneForm();
}

function validateZoneForm() {
  clearZoneFormErrors();

  const name = zoneForm.name.trim();
  const type = zoneForm.type || 'quadra';

  if (!name) {
    zoneFormErrors.name = 'Informe o nome da zona.';
  }

  if (!type) {
    zoneFormErrors.type = 'Selecione o tipo da zona.';
  }

  return !zoneFormErrors.name && !zoneFormErrors.type;
}

function applyZoneFormApiErrors(err) {
  const apiErrors = err?.response?.data?.errors;
  if (!apiErrors || typeof apiErrors !== 'object') return;

  if (apiErrors.name?.[0]) zoneFormErrors.name = apiErrors.name[0];
  if (apiErrors.type?.[0]) zoneFormErrors.type = apiErrors.type[0];
}

function buildZonePayload() {
  return {
    name: zoneForm.name.trim(),
    type: zoneForm.type || 'quadra',
    color: zoneForm.color || '#3B82F6',
    parent_zone_id: zoneForm.parent_zone_id ? Number(zoneForm.parent_zone_id) : null,
  };
}

async function saveZone() {
  if (!validateZoneForm()) {
    toast.warning('Verifique os campos da zona.');
    return;
  }

  const payload = buildZonePayload();
  savingZone.value = true;

  try {
    let createdZone = null;

    if (editingZone.value) {
      await api.post(`/developments/${route.params.id}/zones/${editingZone.value.id}/update`, payload);
      toast.success('Zona atualizada.');
    } else {
      const { data } = await api.post(`/developments/${route.params.id}/zones`, payload);
      createdZone = data;
      toast.success('Zona criada.');
    }

    closeZoneForm();
    await loadZones();
    drawZonesOnMap();

    if (createdZone) {
      const zone = zones.value.find((z) => z.id === createdZone.id) ?? createdZone;
      pickZoneForMapping(zone);
    }
  } catch (err) {
    applyZoneFormApiErrors(err);
    toast.error(getApiErrorMessage(err, 'Erro ao salvar zona.'));
  } finally {
    savingZone.value = false;
  }
}

async function deleteZone(zone) {
  const ok = await confirm(
    'Excluir zona',
    `Excluir "${zone.name}"? Os lotes dentro dela não serão excluídos.`,
    'Sim, excluir',
  );
  if (!ok) return;

  try {
    await api.post(`/developments/${route.params.id}/zones/${zone.id}/delete`);
    toast.success('Zona excluída.');
    await loadZones();
    if (zoneLayers[zone.id]) {
      map?.removeLayer(zoneLayers[zone.id]);
      delete zoneLayers[zone.id];
    }
  } catch {
    toast.error('Erro ao excluir zona.');
  }
}

const generateLotsZone = ref(null);
const generating = ref(false);
const generateForm = ref({
  quantity: 10,
  start_from: 1,
  area: '',
  total_value: 0,
  pattern: '',
});

const previewLotNumber = computed(() => {
  const zone = generateLotsZone.value;
  const pattern = generateForm.value.pattern || form.value.lot_number_pattern || '{zona}-L{numero2}';
  if (!zone) return pattern;

  const num = parseInt(generateForm.value.start_from, 10) || 1;
  return pattern
    .replace('{zona}', zone.name)
    .replace('{numero}', String(num))
    .replace('{numero2}', String(num).padStart(2, '0'))
    .replace('{numero3}', String(num).padStart(3, '0'));
});

function openGenerateLots(zone) {
  if (!canGenerateLotsInZone(zone)) {
    toast.warning(generateLotsBlockedReason(zone));
    return;
  }

  generateLotsZone.value = zone;
  generateForm.value = {
    quantity: 10,
    start_from: 1,
    area: '',
    total_value: 0,
    pattern: '',
  };
}

async function doGenerateLots() {
  generating.value = true;
  try {
    const { data } = await api.post(
      `/developments/${route.params.id}/zones/${generateLotsZone.value.id}/generate-lots`,
      {
        quantity: parseInt(generateForm.value.quantity, 10),
        start_from: parseInt(generateForm.value.start_from, 10) || 1,
        area: generateForm.value.area ? parseFloat(generateForm.value.area) : null,
        total_value: generateForm.value.total_value || null,
        pattern: generateForm.value.pattern || null,
      },
    );
    toast.success(`${data.created} lotes gerados com sucesso!`);
    generateLotsZone.value = null;
    await loadZones();
    await loadLots();
    drawLotsOnMap();
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Erro ao gerar lotes.');
  } finally {
    generating.value = false;
  }
}

async function loadItem() {
  if (!isEdit.value) return;

  loading.value = true;
  try {
    const { data } = await api.get(`/developments/${route.params.id}`);
    const item = data.data ?? data;

    form.value = {
      name: item.name ?? '',
      description: item.description ?? '',
      location: item.location ?? '',
      status: item.status ?? 'active',
      is_featured: Boolean(item.is_featured),
      down_payment_percent: String(item.down_payment_percent ?? 20),
      lot_number_pattern: item.lot_number_pattern ?? '{zona}-L{numero2}',
      coordinates: item.coordinates ?? null,
      map_center: item.map_center ?? null,
      map_zoom: item.map_zoom ?? 17,
      map_color: item.map_color ?? defaultPerimeterColor,
    };
  } catch {
    toast.error('Erro ao carregar empreendimento');
    router.push({ name: 'developments.index' });
  } finally {
    loading.value = false;
  }
}

async function submit() {
  saving.value = true;
  try {
    if (isEdit.value) {
      await api.post(`/developments/${route.params.id}/update`, form.value);
      toast.success('Empreendimento atualizado.');
    } else {
      const { data } = await api.post('/developments', form.value);
      const id = (data.data ?? data).id;
      toast.success('Empreendimento criado.');
      router.push({ name: 'developments.edit', params: { id } });
    }
  } catch {
    toast.error('Erro ao salvar empreendimento.');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadItem();
  await loadZones();
  await loadStreets();
  await loadLots();
  await nextTick();
  await initMap();
  if (zones.value.length) drawZonesOnMap();
  if (streets.value.length) drawStreetsOnMap();
  drawLotsOnMap();
});

watch(() => form.value.map_color, () => {
  if (drawingMode.value === 'perimeter') {
    refreshTempPolyline(perimeterPoints.value.length >= 3);
    refreshVertexMarkerStyles();
    return;
  }

  if (form.value.coordinates?.length >= 3) {
    drawPerimeterOnMap(form.value.coordinates);
  }
});

watch(isMapFullscreen, async (active) => {
  if (!active) {
    showZoneMapPicker.value = false;

    if (fullscreenResizeHandler) {
      window.removeEventListener('resize', fullscreenResizeHandler);
      fullscreenResizeHandler = null;
    }
  } else {
    fullscreenResizeHandler = () => refreshMapLayout();
    window.addEventListener('resize', fullscreenResizeHandler);
  }

  await nextTick();
  refreshMapLayout();
  window.setTimeout(refreshMapLayout, 150);
  window.setTimeout(refreshMapLayout, 450);
});

watch(showZoneMapPicker, async () => {
  if (!isMapFullscreen.value) return;

  await nextTick();
  refreshMapLayout();
});

onUnmounted(() => {
  mapReady.value = false;

  if (fullscreenResizeHandler) {
    window.removeEventListener('resize', fullscreenResizeHandler);
    fullscreenResizeHandler = null;
  }

  if (locationMarker && map) {
    map.removeLayer(locationMarker);
    locationMarker = null;
  }
  map?.remove();
  map = null;
  cursorPreview.unbind();
});
</script>
