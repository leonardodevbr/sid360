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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
          <CurrencyInput
            v-model="form.base_price_per_m2"
            label="Valor base do m²"
          />
        </div>
        <p class="text-xs text-slate-400">
          Usado para calcular o valor dos lotes quando a zona não tiver valor próprio por m².
        </p>
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

            <div
              v-if="generateLotsZone && genMode === 'geometric'"
              class="map-lot-gen-panel"
            >
              <div class="map-lot-gen-panel-header">
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-slate-800">
                    Gerar lotes — {{ generateLotsZone.name }}
                  </p>
                  <p class="mt-0.5 text-xs text-slate-500">
                    Ajuste abaixo e veja o resultado no mapa em tempo real
                  </p>
                </div>
                <button
                  type="button"
                  class="shrink-0 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                  aria-label="Fechar"
                  @click="closeGenerateLotsModal"
                >
                  <XMarkIcon class="h-4 w-4" />
                </button>
              </div>

              <div class="map-lot-gen-panel-body">
                <div v-if="!blockEdges.length" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-700">
                  Esta quadra precisa ter a área desenhada no mapa.
                </div>

                <template v-else>
                  <div class="grid grid-cols-2 gap-2">
                    <Input v-model.number="geoForm.lotDepth" type="number" label="Profundidade (m)" />
                    <Input
                      v-if="geoForm.widthMode === 'equal'"
                      v-model.number="geoForm.lotWidth"
                      type="number"
                      label="Largura (m)"
                    />
                  </div>

                  <div class="mt-3">
                    <p class="text-xs font-semibold text-slate-700">Divisão ao longo da frente</p>
                    <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                      <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-xs font-semibold transition-colors"
                        :class="geoForm.widthMode === 'equal'
                          ? 'border-[#c9a84c] bg-amber-50 text-[#1a3a28]'
                          : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        @click="setGeoWidthMode('equal')"
                      >
                        Larguras iguais
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-xs font-semibold transition-colors"
                        :class="geoForm.widthMode === 'custom'
                          ? 'border-[#c9a84c] bg-amber-50 text-[#1a3a28]'
                          : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        @click="setGeoWidthMode('custom')"
                      >
                        Personalizadas
                      </button>
                    </div>
                  </div>

                  <div
                    v-if="geoFrontLengthM > 0"
                    class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-[11px] text-slate-600"
                  >
                    <span class="font-semibold text-slate-700">Frente: {{ formatMeters(geoFrontLengthM) }}</span>
                    <span v-if="geoSlicePlan.widths.length">
                      · {{ geoSlicePlan.widths.length }} lote(s)
                      · {{ geoSlicePlan.widths.map((w) => `${w}m`).join(' + ') }}
                    </span>
                    <p v-if="geoSlicePlan.trimmed" class="mt-1 text-amber-700">
                      Algumas larguras foram cortadas para caber na frente.
                    </p>
                  </div>

                  <div
                    v-if="geoForm.widthMode === 'equal' && geoSlicePlan.remainder >= 0.5"
                    class="mt-2"
                  >
                    <p class="text-[11px] font-medium text-slate-600">Sobra de {{ formatMeters(geoSlicePlan.remainder) }}</p>
                    <div class="mt-1 grid grid-cols-2 gap-1.5">
                      <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-[11px] font-semibold transition-colors"
                        :class="geoForm.remainderSide === 'start'
                          ? 'border-[#c9a84c] bg-amber-50 text-[#1a3a28]'
                          : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        @click="setGeoRemainderSide('start')"
                      >
                        No início
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-[11px] font-semibold transition-colors"
                        :class="geoForm.remainderSide === 'end'
                          ? 'border-[#c9a84c] bg-amber-50 text-[#1a3a28]'
                          : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        @click="setGeoRemainderSide('end')"
                      >
                        No final
                      </button>
                    </div>
                  </div>

                  <div v-if="geoForm.widthMode === 'custom'" class="mt-2 space-y-1.5">
                    <div
                      v-for="(width, index) in geoForm.customWidths"
                      :key="`geo-width-${index}`"
                      class="flex items-end gap-1.5"
                    >
                      <Input
                        v-model.number="geoForm.customWidths[index]"
                        type="number"
                        :label="`Lote ${index + 1} (m)`"
                        class="min-w-0 flex-1"
                      />
                      <button
                        type="button"
                        class="mb-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 hover:text-red-500 disabled:opacity-40"
                        :disabled="geoForm.customWidths.length <= 1"
                        :aria-label="`Remover lote ${index + 1}`"
                        @click="removeCustomWidthRow(index)"
                      >
                        <XMarkIcon class="h-4 w-4" />
                      </button>
                    </div>

                    <div class="flex flex-wrap gap-1.5 pt-1">
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-dashed border-slate-300 px-2 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                        @click="addCustomWidthRow"
                      >
                        <PlusIcon class="h-3.5 w-3.5" />
                        Adicionar lote
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                        @click="fillCustomWidthsFromEqual"
                      >
                        Preencher iguais
                      </button>
                      <button
                        v-if="geoFrontLengthM > 0 && geoForm.customWidths.length === 2"
                        type="button"
                        class="rounded-lg border border-slate-200 px-2 py-1.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                        @click="splitCustomWidthsHalfHalf"
                      >
                        Metade / metade
                      </button>
                    </div>

                    <div
                      v-if="geoCustomWidthsRemainder >= 0.5"
                      class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2"
                    >
                      <p class="text-[11px] font-medium text-amber-800">
                        Sobra de {{ formatMeters(geoCustomWidthsRemainder) }} na frente
                      </p>
                      <div class="mt-1 grid grid-cols-2 gap-1.5">
                        <button
                          type="button"
                          class="rounded-lg border px-2 py-1.5 text-[11px] font-semibold transition-colors"
                          :class="geoForm.remainderSide === 'start'
                            ? 'border-[#c9a84c] bg-white text-[#1a3a28]'
                            : 'border-amber-200 bg-white/70 text-amber-900 hover:bg-white'"
                          @click="setGeoRemainderSide('start')"
                        >
                          Somar no 1º lote
                        </button>
                        <button
                          type="button"
                          class="rounded-lg border px-2 py-1.5 text-[11px] font-semibold transition-colors"
                          :class="geoForm.remainderSide === 'end'
                            ? 'border-[#c9a84c] bg-white text-[#1a3a28]'
                            : 'border-amber-200 bg-white/70 text-amber-900 hover:bg-white'"
                          @click="setGeoRemainderSide('end')"
                        >
                          Somar no último
                        </button>
                      </div>
                    </div>
                  </div>

                  <div class="mt-3">
                    <button
                      type="button"
                      class="w-full rounded-lg border px-2.5 py-2 text-left text-xs transition-colors"
                      :class="geoForm.reverseFrontEdge
                        ? 'border-[#c9a84c] bg-amber-50 text-[#1a3a28] ring-1 ring-[#c9a84c]/40'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                      @click="toggleGeoReverseFrontEdge"
                    >
                      <span class="block font-semibold">Inverter sentido</span>
                      <span class="mt-0.5 block text-[11px] text-slate-400">
                        Ordem dos lotes na frente
                      </span>
                    </button>
                  </div>

                  <div class="mt-3">
                    <p class="text-xs font-semibold text-slate-700">Qual lado dá para a rua?</p>
                    <p class="mt-0.5 text-[11px] leading-snug text-slate-400">
                      Clique no lado numerado no mapa ou escolha abaixo. A rua próxima é sugerida automaticamente.
                    </p>
                    <div class="mt-2 space-y-1.5">
                      <button
                        v-for="edge in blockEdges"
                        :key="edge.index"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg border px-2.5 py-2 text-left transition-colors"
                        :class="geoForm.frontEdgeIndex === edge.index
                          ? 'border-[#c9a84c] bg-amber-50 ring-1 ring-[#c9a84c]/40'
                          : 'border-slate-200 bg-white hover:bg-slate-50'"
                        @click="selectFrontEdge(edge.index)"
                        @mouseenter="hoverFrontEdge(edge.index)"
                        @mouseleave="hoverFrontEdge(null)"
                      >
                        <span
                          class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                          :class="geoForm.frontEdgeIndex === edge.index
                            ? 'bg-[#c9a84c] text-[#1a3a28]'
                            : 'bg-slate-200 text-slate-600'"
                        >
                          {{ edge.index + 1 }}
                        </span>
                        <span class="min-w-0 flex-1">
                          <span class="block text-xs font-medium text-slate-800">{{ edge.lengthMeters }}m</span>
                          <span
                            v-if="edge.nearestStreet"
                            class="block truncate text-[11px] text-emerald-600"
                          >
                            Rua: {{ edge.nearestStreet }}
                            <span v-if="edge.nearestStreetDistance != null" class="text-slate-400">
                              (~{{ edge.nearestStreetDistance }}m)
                            </span>
                          </span>
                          <span v-else class="block text-[11px] text-slate-400">Sem rua cadastrada próxima</span>
                        </span>
                      </button>
                    </div>
                  </div>

                  <div class="mt-3 grid grid-cols-2 gap-2">
                    <Input v-model.number="geoForm.start_from" type="number" label="Nº inicial" />
                    <CurrencyInput v-model="geoForm.total_value" label="Valor/lote" />
                  </div>
                  <p v-if="generateLotsEffectivePricePerM2" class="mt-1 text-[11px] text-slate-400">
                    Calculado com {{ formatPricePerM2Label(generateLotsEffectivePricePerM2) }}/m²
                  </p>

                  <div
                    v-if="previewLots.length"
                    class="mt-3 rounded-lg bg-emerald-50 px-2.5 py-2 text-xs text-emerald-700"
                  >
                    {{ previewLots.length }} lote(s) no preview
                    <span v-if="previewLots.some((l) => l.clipped)"> · alguns recortados nas bordas</span>
                  </div>
                  <p
                    v-else-if="geoForm.frontEdgeIndex == null"
                    class="mt-3 text-[11px] text-amber-600"
                  >
                    Selecione o lado da rua para ver o preview dos lotes
                  </p>
                  <p v-else-if="previewing" class="mt-3 text-[11px] text-slate-500">
                    Calculando preview...
                  </p>
                </template>
              </div>

              <div v-if="blockEdges.length" class="map-lot-gen-panel-footer">
                <Button variant="outline" type="button" @click.stop="switchGenMode('simple')">
                  Modo simples
                </Button>
                <Button
                  variant="primary"
                  type="button"
                  class="flex-1"
                  :loading="generating"
                  :disabled="generating || !previewLots.length"
                  @click.stop="doGenerateGeometricLots"
                >
                  {{ generating ? 'Gerando...' : `Gerar ${previewLots.length || 0} lotes` }}
                </Button>
              </div>
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
                  v-if="drawingMode && showAreaShapeTools"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:justify-start sm:px-3 sm:text-xs"
                  :class="drawingShapeMode === 'free'
                    ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                    : 'border-slate-200 bg-slate-50 text-slate-500 hover:bg-white'"
                  @click="setDrawingShapeMode('free')"
                >
                  Livre
                </button>
                <button
                  v-if="drawingMode && showAreaShapeTools"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:justify-start sm:px-3 sm:text-xs"
                  :class="drawingShapeMode === 'rectangle'
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                    : 'border-slate-200 bg-slate-50 text-slate-500 hover:bg-white'"
                  @click="setDrawingShapeMode('rectangle')"
                >
                  Retângulo
                </button>
                <button
                  v-if="drawingMode && (perimeterPoints.length || rectangleAnchor)"
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
                  {{ drawingMode === 'zone' ? 'Cancelar edição' : drawingMode === 'street-axis' ? 'Cancelar traçado' : 'Cancelar desenho' }}
                </button>
                <button
                  v-if="drawingMode && canSaveDrawing"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--save map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] disabled:cursor-not-allowed disabled:opacity-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="finishDrawing()"
                >
                  {{ drawingMode === 'street-axis' ? 'Concluir traçado' : 'Salvar demarcação' }}
                </button>
                <button
                  v-if="!drawingMode && !measureMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-violet-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-violet-700 hover:bg-violet-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="startMeasureMode"
                >
                  Trena
                </button>
                <button
                  v-if="measureMode && measurePoints.length"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-amber-600 hover:bg-amber-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="undoMeasurePoint"
                >
                  <ArrowUturnLeftIcon class="h-3.5 w-3.5 shrink-0" />
                  Desfazer ponto
                </button>
                <button
                  v-if="measureMode && measurePoints.length >= 2"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--save map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] sm:justify-start sm:px-3 sm:text-xs"
                  @click="commitCurrentMeasure"
                >
                  Fixar medição
                </button>
                <button
                  v-if="measureMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-medium text-amber-700 hover:bg-amber-100 sm:justify-start sm:px-3 sm:text-xs"
                  @click="stopMeasureMode"
                >
                  <XMarkIcon class="h-3.5 w-3.5 shrink-0" />
                  Sair da trena
                </button>
                <button
                  v-if="savedMeasuresCount > 0 && !drawingMode"
                  type="button"
                  class="map-toolbar-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-red-600 hover:bg-red-50 sm:justify-start sm:px-3 sm:text-xs"
                  @click="clearAllMeasures"
                >
                  Limpar trenas
                </button>
                <button
                  v-if="!drawingMode && !measureMode"
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
                  class="hidden text-xs text-slate-500 sm:inline"
                >
                  Perímetro
                  <span v-if="drawingShapeMode === 'rectangle' && !perimeterPoints.length" class="text-slate-400">
                    · {{ rectangleAnchor ? 'Canto oposto' : 'Primeiro canto' }}
                  </span>
                  <span v-else-if="perimeterPoints.length" class="text-slate-400"> · {{ perimeterPoints.length }} pts</span>
                </span>
                <span
                  v-else-if="drawingMode === 'zone'"
                  class="hidden text-xs text-slate-500 sm:inline"
                >
                  {{ drawingZone?.name }}
                  <span v-if="drawingShapeMode === 'rectangle' && !perimeterPoints.length" class="text-slate-400">
                    · {{ rectangleAnchor ? 'Canto oposto' : 'Primeiro canto' }}
                  </span>
                  <span v-else-if="perimeterPoints.length" class="text-slate-400"> · {{ perimeterPoints.length }} pts</span>
                </span>
                <span
                  v-else-if="drawingMode === 'street-axis'"
                  class="hidden text-xs text-slate-500 sm:inline"
                >
                  {{ drawingStreet?.name }}
                  <span v-if="axisPreviewLength" class="text-slate-400"> · {{ axisPreviewLength }} m</span>
                  <span v-if="perimeterPoints.length" class="text-slate-400"> · {{ perimeterPoints.length }} pts</span>
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
                  v-if="!drawingMode && !measureMode"
                  type="button"
                  class="map-toolbar-btn map-toolbar-btn--map map-toolbar-action-btn col-span-2 flex items-center justify-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-medium sm:col-span-1 sm:justify-start sm:px-3 sm:text-xs"
                  :class="hasCustomMapLayerSelection
                    ? 'border-sky-300 bg-sky-50 text-sky-700'
                    : ''"
                  @click="openMapLayerPicker"
                >
                  <Squares2X2Icon class="h-3.5 w-3.5 shrink-0" />
                  Exibir camadas
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

      <div v-if="isEdit" class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
          <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-2 text-left hover:opacity-80"
            @click="streetsSectionExpanded = !streetsSectionExpanded"
          >
            <ChevronDownIcon
              class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
              :class="{ '-rotate-90': !streetsSectionExpanded }"
            />
            <p class="text-sm font-semibold text-slate-700">Ruas do loteamento</p>
            <span class="text-xs text-slate-400">{{ streets.length }} rua(s)</span>
          </button>
          <button
            type="button"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700"
            @click.stop="openStreetForm"
          >
            <PlusIcon class="h-3.5 w-3.5" />
            Nova rua
          </button>
        </div>

        <div v-show="streetsSectionExpanded" class="space-y-2 p-4 sm:p-5">
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
                  <span v-if="street.centerline?.length >= 2 && hasValidStreetPolygon(street.coordinates?.length ?? 0)" class="whitespace-nowrap text-emerald-600">
                    · eixo traçado ({{ street.width || 10 }}m de largura)
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
                class="resource-list-action-btn rounded px-2 py-1.5 text-xs text-sky-600 hover:bg-sky-50"
                @click="startDrawStreetAxis(street)"
              >
                {{ street.centerline?.length >= 2 ? 'Retraçar eixo' : 'Traçar eixo' }}
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
          <p v-if="!streets.length" class="text-xs text-slate-400">Nenhuma rua cadastrada ainda.</p>
        </div>
      </div>

      <div v-if="isEdit" class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
          <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-2 text-left hover:opacity-80"
            @click="zonesSectionExpanded = !zonesSectionExpanded"
          >
            <ChevronDownIcon
              class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
              :class="{ '-rotate-90': !zonesSectionExpanded }"
            />
            <p class="text-sm font-semibold text-slate-700">
              Zonas (setor / conjunto / quadra)
            </p>
            <span class="text-xs text-slate-400">{{ zones.length }} zona(s)</span>
          </button>
          <button
            type="button"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-action px-3 py-1.5 text-xs font-semibold text-white hover:bg-action-hover"
            @click.stop="openZoneForm"
          >
            <PlusIcon class="h-3.5 w-3.5" />
            Nova zona
          </button>
        </div>

        <div v-show="zonesSectionExpanded" class="space-y-2 p-4 sm:p-5">
          <p class="text-[11px] leading-snug text-slate-400">
            Ordem hierárquica: setor engloba conjuntos e quadras. Use zona pai ao cadastrar ou editar.
          </p>

          <div
            v-for="{ zone, depth } in hierarchicalZones"
            :key="zone.id"
            class="resource-list-item flex flex-col gap-3 rounded-lg border border-slate-200 p-3 sm:flex-row sm:items-center sm:gap-3 sm:py-2.5"
            :class="depth > 0 ? 'border-slate-100 bg-slate-50/60' : ''"
          >
            <div class="flex min-w-0 items-start gap-3 sm:flex-1" :style="{ paddingLeft: `${depth * 18}px` }">
              <div
                v-if="depth > 0"
                class="mt-2 h-px w-3 shrink-0 bg-slate-300"
                aria-hidden="true"
              />
              <div class="mt-1 h-3 w-3 shrink-0 rounded-full" :style="{ background: zone.color }" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold tracking-wide text-slate-800">{{ buildZoneTitleLabel(zone) }}</p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-slate-400">
                  <span class="whitespace-nowrap">{{ zoneTypeLabel(zone.type) }}</span>
                  <span v-if="getZoneParentName(zones, zone)" class="whitespace-nowrap">
                    · em <strong>{{ getZoneParentName(zones, zone) }}</strong>
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
          <p v-if="!zones.length" class="text-xs text-slate-400">Nenhuma zona cadastrada ainda.</p>
        </div>
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
          placeholder="Nenhuma (zona de nível superior)"
          :searchable="false"
        />
        <p v-if="parentZoneOptions.length" class="text-xs text-slate-400">
          Opcional — ex: Conjunto ou Quadra dentro de um Setor; Quadra dentro de um Conjunto.
        </p>
        <p v-else-if="zoneForm.type === 'setor'" class="text-xs text-slate-400">
          Setores ficam no topo da hierarquia e não possuem zona pai.
        </p>
        <CurrencyInput
          v-model="zoneForm.price_per_m2"
          label="Valor do m² (zona)"
        />
        <p class="text-xs text-slate-400">
          Opcional — sobrescreve o valor base do empreendimento para lotes desta zona.
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
        <Input
          v-model.number="streetForm.width"
          type="number"
          min="1"
          step="0.5"
          label="Largura da rua (m)"
        />
        <p class="mt-1 text-xs text-slate-400">
          A largura é usada para gerar a faixa da rua a partir do eixo que você traçar.
        </p>
      </div>
      <div class="mt-3">
        <SelectInput
          v-model="streetForm.end_cap"
          label="Extremidades do eixo"
          :options="streetEndCapOptions"
          :searchable="false"
        />
        <p class="mt-1 text-xs text-slate-400">
          Define se as pontas da faixa da rua ficam arredondadas ou quadradas.
        </p>
      </div>
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
      <div class="mt-4 flex flex-wrap justify-end gap-2">
        <Button
          v-if="editingStreet?.centerline?.length >= 2"
          variant="outline"
          :disabled="savingStreet"
          @click="recalcStreetWidthFromForm"
        >
          Recalcular faixa no mapa
        </Button>
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
      :is-open="showMapLayerPicker"
      title="Exibir camadas no mapa"
      @close="closeMapLayerPicker"
    >
      <p class="text-xs text-slate-500">
        Escolha quais camadas devem aparecer no mapa.
      </p>

      <div class="mt-3 flex gap-2">
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="selectAllMapLayersInDraft"
        >
          Marcar todos
        </button>
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="clearAllMapLayersInDraft"
        >
          Limpar seleção
        </button>
      </div>

      <div class="mt-3 space-y-2">
        <button
          v-for="option in mapLayerOptions"
          :key="option.id"
          type="button"
          class="flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left transition-colors"
          :class="mapLayerPickerDraft.includes(option.id)
            ? 'border-sky-300 bg-sky-50'
            : 'border-slate-200 bg-white hover:bg-slate-50'"
          @click="toggleMapLayerDraft(option.id)"
        >
          <span>
            <span class="block text-sm font-medium text-slate-800">{{ option.label }}</span>
            <span class="block text-xs text-slate-400">
              {{ mapLayerItemCount(option.id) }} no mapa
            </span>
          </span>
          <span
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded border"
            :class="mapLayerPickerDraft.includes(option.id)
              ? 'border-sky-600 bg-sky-600 text-white'
              : 'border-slate-300 bg-white text-transparent'"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
          </span>
        </button>
      </div>

      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="closeMapLayerPicker">Cancelar</Button>
        <Button variant="primary" @click="applyMapLayerPicker">Aplicar</Button>
      </div>
    </Modal>

    <Modal
      :is-open="!!generateLotsZone && genMode === 'simple'"
      :title="generateLotsZone ? `Gerar lotes — ${generateLotsZone.name}` : 'Gerar lotes'"
      @close="closeGenerateLotsModal"
    >
      <div class="space-y-3">
        <div class="mb-4 flex gap-2">
          <button
            type="button"
            class="flex-1 rounded-lg border px-3 py-2 text-sm font-semibold"
            :class="genMode === 'simple' ? 'border-emerald-400 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-500'"
            @click="switchGenMode('simple')"
          >
            Simples (sem mapa)
          </button>
          <button
            type="button"
            class="flex-1 rounded-lg border px-3 py-2 text-sm font-semibold"
            :class="genMode === 'geometric' ? 'border-emerald-400 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-500'"
            @click="switchGenMode('geometric')"
          >
            Com polígonos no mapa
          </button>
        </div>

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
        <p v-if="generateLotsEffectivePricePerM2" class="text-xs text-slate-400">
          Calculado com {{ formatPricePerM2Label(generateLotsEffectivePricePerM2) }}/m²
          <span v-if="generateForm.area"> · {{ generateForm.area }} m²</span>
        </p>
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
        <Button variant="outline" @click="closeGenerateLotsModal">Cancelar</Button>
        <Button variant="primary" :disabled="generating" @click="doGenerateLots()">
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
import { useAlert, swalDefaultConfig } from '@/composables/useAlert';
import Swal from 'sweetalert2';
import { useMapFullscreen } from '@/composables/useMapFullscreen';
import { developmentStatusFormOptions } from '@/utils/labels';
import { setupMapBaseLayers, ensureMapRotation, configureMapRotation, refreshMapDisplay, hideMapScrollZoomHint, showMapScrollZoomHint, eventToMapLatLng } from '@/utils/mapLayers';
import {
  arePointsInsideOrOnPolygon,
  formatMeters,
  formatAreaM2,
  formatPolygonAreaM2,
  getInvalidPointsInsidePolygon,
  getPolygonEdgesMeters,
  isPointInsideOrOnPolygon,
  normalizePolygonCoordinates,
} from '@/utils/mapGeometry';
import { lotStatusLabel } from '@/utils/status';
import {
  ALL_MAP_LAYER_IDS,
  getZoneMapLayerId,
  isMapLayerVisible,
  MAP_LAYER_LOTS,
  MAP_LAYER_OPTIONS,
  MAP_LAYER_PERIMETER,
  MAP_LAYER_STREETS,
  setLeafletLayerVisibility,
} from '@/utils/mapLayerVisibility';
import {
  buildZoneMetaLabel,
  buildZoneTitleLabel,
  buildZoneHierarchyList,
  canGenerateLotsInZone,
  generateLotsBlockedReason,
  getValidParentZones,
  getZoneParentName,
  getZoneTypeRank,
  ZONE_TYPE_OPTIONS,
  zoneTypeLabel as zoneTypeLabelHelper,
} from '@/utils/zone';
import { createCursorPreviewController } from '@/utils/mapDrawingPreview';
import {
  collectMapSnapHintPoints,
  collectMapSnapIntersectionTargets,
  collectMapSnapSegmentTargets,
  collectMapSnapTargets,
  MAP_INTERSECTION_SNAP_PIXEL_RADIUS,
  MAP_SEGMENT_SNAP_PIXEL_RADIUS,
  MAP_SNAP_PIXEL_RADIUS,
  rectangleFromOppositeCorners,
  resolveSnapToleranceMeters,
  resolveSnappedCoordinate,
  findNearestPolygonEdgeInsert,
} from '@/utils/mapVertexSnap';
import {
  subdivideBlockIntoLots,
  enrichBlockEdgesWithStreets,
  resolveSliceWidths,
  divideFrontLengthEqually,
} from '@/utils/lotSubdivision';
import {
  computeLotTotalValueFromArea,
  resolveEffectivePricePerM2,
} from '@/utils/lotPricing';
import { buildStreetPolygon, centerlineLengthMeters, buildStreetNetworkVisualRings, normalizeStreetEndCap } from '@/utils/streetGeometry';
import { formatMoneyMaskFromCents } from '@/utils/format';
import Input from '@/components/Common/Input.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import CurrencyInput from '@/components/Common/CurrencyInput.vue';
import MediaGallery from '@/components/Common/MediaGallery.vue';
import { ArrowLeftIcon, ArrowUturnLeftIcon, ArrowsPointingInIcon, ArrowsPointingOutIcon, ChevronDownIcon, MapIcon, MapPinIcon, PlusIcon, RectangleGroupIcon, Squares2X2Icon, TagIcon, XMarkIcon } from '@heroicons/vue/24/outline';

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
  base_price_per_m2: 0,
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
  if (mode === 'street-axis') {
    return 2;
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
let streetUnionVisualLayer = null;
let lotLayersMap = {};
let previewLayerGroup = null;
let blockEdgeLayerGroup = null;
let snapHintLayerGroup = null;
let axisPreviewLayer = null;
let measureTempLayerGroup = null;
const savedMeasureLayerGroups = [];
let locationMarker = null;
let mapLayersSetup = null;
let fullscreenResizeHandler = null;
const mapPopupActions = new WeakMap();
const drawingMode = ref(null);
const drawingZone = ref(null);
const drawingStreet = ref(null);
const axisStreet = ref(null);
const axisPreviewLength = ref(0);
const measureMode = ref(false);
const measurePoints = ref([]);
const savedMeasuresCount = ref(0);
const locatingUser = ref(false);
const mapReady = ref(false);
const startedFromExistingPolygon = ref(false);
const drawingShapeMode = ref('free');
const rectangleAnchor = ref(null);
let rectangleAnchorMarker = null;
let firstVertexCloseTimer = null;
const cursorPreview = createCursorPreviewController();

function getDrawingStrokeColor() {
  const lineColor = drawingMode.value === 'perimeter'
    ? getPerimeterColor()
    : drawingMode.value === 'street-axis'
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

function getDrawingSnapContext() {
  const isEditingPerimeter = drawingMode.value === 'perimeter' && startedFromExistingPolygon.value;

  return {
    perimeterCoordinates: isEditingPerimeter ? [] : (form.value.coordinates ?? []),
    zones: zones.value,
    streets: streets.value,
    lots: lots.value,
    currentDrawingPoints: perimeterPoints.value,
    excludeZoneId: drawingMode.value === 'zone' ? drawingZone.value?.id : null,
    excludeStreetId: drawingMode.value === 'street-axis' ? drawingStreet.value?.id : null,
  };
}

function applyDrawingSnap(lat, lng, {
  excludeDrawingVertexIndex = null,
  includeDrawingPoints = true,
  includeDrawingSegments = true,
} = {}) {
  const context = getDrawingSnapContext();
  const targets = collectMapSnapTargets({
    ...context,
    includeDrawingPoints,
    excludeDrawingVertexIndex,
  });
  const segmentTargets = collectMapSnapSegmentTargets({
    ...context,
    includeDrawingSegments,
  });
  const intersectionTargets = collectMapSnapIntersectionTargets(segmentTargets);
  const vertexToleranceMeters = resolveSnapToleranceMeters(map, lat, lng, {
    pixelRadius: MAP_SNAP_PIXEL_RADIUS,
  });
  const intersectionToleranceMeters = resolveSnapToleranceMeters(map, lat, lng, {
    pixelRadius: MAP_INTERSECTION_SNAP_PIXEL_RADIUS,
  });
  const segmentToleranceMeters = resolveSnapToleranceMeters(map, lat, lng, {
    pixelRadius: MAP_SEGMENT_SNAP_PIXEL_RADIUS,
  });

  return resolveSnappedCoordinate(lat, lng, {
    targets,
    intersectionTargets,
    segmentTargets,
    vertexToleranceMeters,
    intersectionToleranceMeters,
    segmentToleranceMeters,
  });
}

function clearSnapHintMarkers() {
  if (snapHintLayerGroup && map) {
    map.removeLayer(snapHintLayerGroup);
    snapHintLayerGroup = null;
  }
}

function syncSnapHintMarkers() {
  if (!map || !L) {
    return;
  }

  clearSnapHintMarkers();

  if (drawingMode.value !== 'zone' && drawingMode.value !== 'perimeter') {
    return;
  }

  const context = getDrawingSnapContext();
  const hints = collectMapSnapHintPoints(context);

  if (!hints.length) {
    return;
  }

  const bounds = map.getBounds();
  snapHintLayerGroup = L.featureGroup();

  hints.forEach((hint) => {
    const [lat, lng] = hint.coord;

    if (!bounds.contains([lat, lng])) {
      return;
    }

    const isIntersection = hint.kind === 'intersection';

    snapHintLayerGroup.addLayer(L.marker([lat, lng], {
      interactive: false,
      keyboard: false,
      zIndexOffset: isIntersection ? 1250 : 1150,
      icon: L.divIcon({
        className: 'map-snap-hint-icon',
        html: `<span class="map-snap-hint-indicator${isIntersection ? ' map-snap-hint-indicator--intersection' : ''}"></span>`,
        iconSize: isIntersection ? [12, 12] : [10, 10],
        iconAnchor: isIntersection ? [6, 6] : [5, 5],
      }),
    }));
  });

  if (snapHintLayerGroup.getLayers().length) {
    snapHintLayerGroup.addTo(map);
  } else {
    clearSnapHintMarkers();
  }
}

function clearRectangleDrawingState() {
  rectangleAnchor.value = null;

  if (rectangleAnchorMarker && map) {
    map.removeLayer(rectangleAnchorMarker);
    rectangleAnchorMarker = null;
  }
}

function resetDrawingShapeMode() {
  drawingShapeMode.value = 'free';
  clearRectangleDrawingState();
}

function setDrawingShapeMode(mode) {
  if (drawingShapeMode.value === mode) {
    return;
  }

  if (mode === 'rectangle' && (startedFromExistingPolygon.value || perimeterPoints.value.length > 0)) {
    return;
  }

  drawingShapeMode.value = mode;
  clearRectangleDrawingState();
  syncDrawingCursorPreview();
}

function showRectangleAnchorMarker(coord, color) {
  if (!map || !L) {
    return;
  }

  if (rectangleAnchorMarker) {
    map.removeLayer(rectangleAnchorMarker);
    rectangleAnchorMarker = null;
  }

  rectangleAnchorMarker = L.marker(coord, {
    draggable: false,
    icon: buildVertexIcon(color, false, { drawOnly: true, interactive: false }),
    zIndexOffset: 1500,
  }).addTo(map);
}

function handleRectangleMapClick(lat, lng) {
  const color = getDrawingBaseColor();

  if (!rectangleAnchor.value) {
    rectangleAnchor.value = [lat, lng];
    showRectangleAnchorMarker([lat, lng], color);
    toast.info('Clique no canto oposto para formar o retângulo.');
    syncDrawingCursorPreview();
    return true;
  }

  const rectangle = rectangleFromOppositeCorners(rectangleAnchor.value, [lat, lng]);
  clearRectangleDrawingState();
  drawingShapeMode.value = 'free';
  preloadDrawingPoints(rectangle, color);
  toast.info('Retângulo criado. Arraste os vértices para ajustar ao terreno.');
  return true;
}

function canUseAreaShapeTools() {
  return (drawingMode.value === 'perimeter' || drawingMode.value === 'zone')
    && !startedFromExistingPolygon.value
    && perimeterPoints.value.length === 0;
}

const showAreaShapeTools = computed(() => canUseAreaShapeTools());

function syncDrawingCursorPreview() {
  if (!map || !L || !drawingMode.value) {
    if (!measureMode.value) {
      cursorPreview.unbind();
    }
    clearSnapHintMarkers();
    return;
  }

  const rectanglePreviewActive = drawingShapeMode.value === 'rectangle' && Boolean(rectangleAnchor.value);

  if (startedFromExistingPolygon.value && !rectanglePreviewActive) {
    cursorPreview.bind(map, L, {
      isActive: () => Boolean(drawingMode.value),
      getLastPoint: () => null,
      resolveCursorLatLng: (cursorLatLng) => {
        if (!cursorLatLng) {
          return cursorLatLng;
        }

        return applyDrawingSnap(cursorLatLng.lat, cursorLatLng.lng, {
          includeDrawingPoints: false,
          includeDrawingSegments: false,
        });
      },
      getStrokeColor: getDrawingStrokeColor,
      getInvalid: () => false,
      isCursorInvalid: () => false,
    });
    syncSnapHintMarkers();
    return;
  }

  cursorPreview.bind(map, L, {
    isActive: () => {
      if (rectanglePreviewActive) {
        return true;
      }

      if (startedFromExistingPolygon.value) {
        return false;
      }

      return Boolean(drawingMode.value);
    },
    getLastPoint: () => {
      if (rectanglePreviewActive) {
        return rectangleAnchor.value;
      }

      const points = perimeterPoints.value;
      return points.length ? points[points.length - 1] : null;
    },
    getPreviewPolygon: (cursorLatLng) => {
      if (!rectanglePreviewActive || !rectangleAnchor.value || !cursorLatLng) {
        return null;
      }

      return rectangleFromOppositeCorners(
        rectangleAnchor.value,
        [cursorLatLng.lat, cursorLatLng.lng],
      );
    },
    resolveCursorLatLng: (cursorLatLng) => {
      if (!cursorLatLng) {
        return cursorLatLng;
      }

      return applyDrawingSnap(cursorLatLng.lat, cursorLatLng.lng);
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
  syncSnapHintMarkers();
}

function syncMeasureCursorPreview() {
  if (!map || !L || !measureMode.value) {
    if (!drawingMode.value) {
      cursorPreview.unbind();
    }
    return;
  }

  cursorPreview.bind(map, L, {
    isActive: () => measureMode.value && measurePoints.value.length >= 1,
    getLastPoint: () => {
      const points = measurePoints.value;
      return points.length ? points[points.length - 1] : null;
    },
    getStrokeColor: () => '#7c3aed',
    getInvalid: () => false,
    isCursorInvalid: () => false,
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

const canSaveDrawing = computed(() => {
  if (!drawingMode.value) {
    return false;
  }

  if (drawingMode.value === 'street-axis') {
    return perimeterPoints.value.length >= 2;
  }

  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);

  if (perimeterPoints.value.length < minimumPoints) {
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
  map.on('moveend zoomend', () => {
    if (drawingMode.value === 'zone' || drawingMode.value === 'perimeter') {
      syncSnapHintMarkers();
    }
  });
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
  bindPopupActionButton(popupElement, '[data-map-generate-lots]', actions.onGenerateLots);
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
    if (drawingMode.value || measureMode.value) return;
    blurPolygonPath(layer);
    L.DomEvent.stopPropagation(e);
  });

  layer.on('click', (e) => {
    if (drawingMode.value || measureMode.value) return;
    L.DomEvent.stopPropagation(e);
    layer.closeTooltip?.();
    blurPolygonPath(layer);
  });

  layer.on('popupclose', () => {
    blurPolygonPath(layer);
  });
}

function buildZonePopupHtml(zone) {
  const canGenerate = canGenerateLotsInZone(zone);

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
        ${canGenerate ? `
        <button type="button" class="map-feature-popup-btn map-feature-popup-btn--accent" data-map-generate-lots>
          Gerar lotes
        </button>
        ` : ''}
        <button type="button" class="map-feature-popup-btn map-feature-popup-btn--danger" data-map-clear>
          Limpar demarcação
        </button>
      </div>
    </div>
  `;
}

function buildPerimeterPopupHtml(coords) {
  const areaLabel = formatPolygonAreaM2(coords);

  return `
    <div class="map-feature-popup">
      <p class="map-feature-popup-title">Perímetro do empreendimento</p>
      <p class="map-feature-popup-meta">
        Limite geral do empreendimento no mapa${areaLabel ? ` · ${areaLabel}` : ''}
      </p>
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
  const areaLabel = formatPolygonAreaM2(street.coordinates);

  return `
    <div class="map-feature-popup">
      <p class="map-feature-popup-title">${escapeHtml(street.name)}</p>
      <p class="map-feature-popup-meta">Rua${areaLabel ? ` · ${areaLabel}` : ''}</p>
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
  const blockLabel = lot.block || lot.zone?.name;
  return blockLabel ? `${blockLabel} · Lote ${lot.number}` : `Lote ${lot.number}`;
}

function buildLotPopupHtml(lot) {
  const storedArea = lot.area_computed ?? lot.area;
  const areaLabel = storedArea != null && storedArea !== ''
    ? formatAreaM2(storedArea, { approximate: false })
    : formatPolygonAreaM2(lot.coordinates);

  return `
    <div class="map-feature-popup">
      <p class="map-feature-popup-title">${escapeHtml(buildLotLabel(lot))}</p>
      <p class="map-feature-popup-meta">
        ${escapeHtml(lotStatusLabel(lot.status))}${areaLabel ? ` · ${areaLabel}` : ''}
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

function syncMapOverlayInteraction() {
  setMapOverlaysPointerEvents(!drawingMode.value && !measureMode.value);
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

function mappedStreetsCount() {
  return streets.value.filter(
    (street) => hasValidStreetPolygon(street.coordinates?.length ?? 0),
  ).length;
}

function mappedLotsCount() {
  return lots.value.filter((lot) => {
    const coords = normalizePolygonCoordinates(lot.coordinates);
    return coords && coords.length >= 3;
  }).length;
}

function mapLayerItemCount(layerId) {
  if (layerId === MAP_LAYER_PERIMETER) {
    return form.value.coordinates?.length >= 3 ? 1 : 0;
  }

  if (layerId === MAP_LAYER_STREETS) {
    return mappedStreetsCount();
  }

  if (layerId === MAP_LAYER_LOTS) {
    return mappedLotsCount();
  }

  if (layerId.startsWith('zone:')) {
    return mappedZonesCountByType(layerId.replace('zone:', ''));
  }

  return 0;
}

function syncMapLayerVisibility() {
  if (!map) {
    return;
  }

  setLeafletLayerVisibility(
    map,
    perimeterLayer,
    isMapLayerVisible(visibleMapLayers.value, MAP_LAYER_PERIMETER),
  );

  Object.entries(zoneLayers).forEach(([zoneId, layer]) => {
    const zone = zones.value.find((item) => String(item.id) === String(zoneId));
    const layerId = zone ? getZoneMapLayerId(zone.type) : null;
    const visible = layerId
      ? isMapLayerVisible(visibleMapLayers.value, layerId)
      : true;

    setLeafletLayerVisibility(map, layer, visible);
  });

  const streetsVisible = isMapLayerVisible(visibleMapLayers.value, MAP_LAYER_STREETS);
  setLeafletLayerVisibility(map, streetUnionVisualLayer, streetsVisible);

  Object.values(streetLayersMap).forEach((layer) => {
    setLeafletLayerVisibility(map, layer, streetsVisible);
  });

  const lotsVisible = isMapLayerVisible(visibleMapLayers.value, MAP_LAYER_LOTS);
  Object.values(lotLayersMap).forEach((layer) => {
    setLeafletLayerVisibility(map, layer, lotsVisible);
  });

  if (streetsVisible || lotsVisible) {
    bringZoneLayersToFront();
  }

  if (lotsVisible) {
    bringLotLayersToFront();
  }
}

function openMapLayerPicker() {
  mapLayerPickerDraft.value = [...visibleMapLayers.value];
  showMapLayerPicker.value = true;
}

function closeMapLayerPicker() {
  showMapLayerPicker.value = false;
}

function toggleMapLayerDraft(layerId) {
  const index = mapLayerPickerDraft.value.indexOf(layerId);

  if (index >= 0) {
    mapLayerPickerDraft.value.splice(index, 1);
    return;
  }

  mapLayerPickerDraft.value.push(layerId);
}

function selectAllMapLayersInDraft() {
  mapLayerPickerDraft.value = [...ALL_MAP_LAYER_IDS];
}

function clearAllMapLayersInDraft() {
  mapLayerPickerDraft.value = [];
}

function applyMapLayerPicker() {
  visibleMapLayers.value = [...mapLayerPickerDraft.value];
  closeMapLayerPicker();
  syncMapLayerVisibility();
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

function lightenHexColor(hex, amount = 0.22) {
  const normalized = String(hex || '').replace('#', '');
  if (normalized.length !== 6) {
    return hex || defaultStreetColor;
  }

  const channels = normalized.match(/.{2}/g)?.map((part) => parseInt(part, 16)) ?? [];
  if (channels.length !== 3) {
    return hex || defaultStreetColor;
  }

  const mix = (channel) => Math.min(255, Math.round(channel + (255 - channel) * amount));

  return `#${channels.map((channel) => mix(channel).toString(16).padStart(2, '0')).join('')}`;
}

function getStreetLayerStyle(street, { preview = false } = {}) {
  const color = getStreetColor(street);

  return {
    color,
    weight: preview ? 1.5 : 2,
    fillColor: lightenHexColor(color),
    fillOpacity: preview ? 0.48 : 0.42,
    opacity: 0.95,
  };
}

function getDrawingBaseColor() {
  if (drawingMode.value === 'perimeter') {
    return getPerimeterColor();
  }

  if (drawingMode.value === 'street-axis') {
    return getStreetColor(drawingStreet.value);
  }

  return drawingZone.value?.color ?? '#10B981';
}

function canDragVertexMarkers() {
  return Boolean(drawingMode.value) && perimeterPoints.value.length >= 1;
}

function isFirstVertexClosable(marker) {
  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);

  return !startedFromExistingPolygon.value
    && marker._vertexIndex === 0
    && perimeterPoints.value.length >= minimumPoints;
}

function buildVertexIcon(color, invalid = false, options = {}) {
  const { closeTarget = false, drawOnly = false, interactive = false } = options;

  return L.divIcon({
    className: `map-vertex-handle-icon${interactive ? ' map-vertex-handle-icon--interactive' : ''}`,
    html: `<span class="map-vertex-handle-wrap"><span class="map-vertex-handle${invalid ? ' map-vertex-handle--invalid' : ''}${closeTarget ? ' map-vertex-handle--close-target' : ''}${drawOnly ? ' map-vertex-handle--draw-only' : ''}" style="--vertex-color:${color}"></span></span>`,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
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

  const iconElement = marker.getElement?.();
  if (iconElement) {
    const interactive = canDragVertexMarkers() || isFirstVertexClosable(marker);
    iconElement.classList.toggle('map-vertex-handle-icon--interactive', interactive);
    iconElement.style.pointerEvents = interactive ? 'auto' : 'none';
  }
}

function getVertexIconOptions(marker) {
  const interactive = canDragVertexMarkers() || isFirstVertexClosable(marker);

  return {
    closeTarget: isFirstVertexClosable(marker),
    drawOnly: !canDragVertexMarkers(),
    interactive,
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
    updateVertexHandleStyle(marker);
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

    const snapped = applyDrawingSnap(latLng.lat, latLng.lng, {
      excludeDrawingVertexIndex: marker._vertexIndex,
      includeDrawingPoints: !startedFromExistingPolygon.value,
      includeDrawingSegments: !startedFromExistingPolygon.value,
    });
    const nextLatLng = { lat: snapped.lat, lng: snapped.lng };

    marker.setLatLng(nextLatLng);
    perimeterPoints.value[marker._vertexIndex] = [snapped.lat, snapped.lng];
    cursorPreview.showSnapIndicator(nextLatLng, snapped.snapped);
    refreshTempPolyline(
      startedFromExistingPolygon.value && perimeterPoints.value.length >= 3,
      { livePreview: true },
    );
    updateVertexHandleStyle(marker);

    if (drawingMode.value === 'street-axis') {
      updateAxisPreviewFromPoints();
    }
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

    cursorPreview.clearSnapIndicator();

    if (!marker._wasDragged && tryClosePolygonOnFirstVertexTap(marker)) {
      return;
    }

    refreshTempPolyline(
      startedFromExistingPolygon.value && perimeterPoints.value.length >= 3,
    );
    refreshVertexMarkerStyles();
    bringVertexMarkersToFront();
    bringEdgeLabelMarkersToFront();

    if (drawingMode.value === 'street-axis') {
      updateAxisPreviewFromPoints();
    }

    syncDrawingCursorPreview();

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

    cursorPreview.clear();
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
    zIndexOffset: 2500,
    icon: buildVertexIcon(markerColor, invalid),
  }).addTo(map);

  marker._vertexIndex = index;
  marker.setIcon(buildVertexIcon(markerColor, invalid, getVertexIconOptions(marker)));
  updateVertexHandleStyle(marker);
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
  refreshVertexMarkerStyles();
  syncDrawingCursorPreview();
}

function canInsertVerticesOnEdge() {
  if (!drawingMode.value || perimeterPoints.value.length < 2) {
    return false;
  }

  if (drawingMode.value === 'street-axis') {
    return true;
  }

  return startedFromExistingPolygon.value || perimeterPoints.value.length >= 3;
}

function bindTempShapeEdgeHandlers(layer) {
  if (!layer || !L) {
    return;
  }

  layer.off('dblclick', onTempShapeDblClickInsertVertex);
  layer.on('dblclick', onTempShapeDblClickInsertVertex);
}

function onTempShapeDblClickInsertVertex(event) {
  L.DomEvent.stopPropagation(event);
  L.DomEvent.preventDefault(event);
  clearFirstVertexCloseTimer();

  const latLng = event.latlng ?? eventToMapLatLng(map, event);
  if (!latLng) {
    return;
  }

  insertVertexOnNearestEdge(latLng.lat, latLng.lng);
}

function insertVertexOnNearestEdge(lat, lng) {
  if (!canInsertVerticesOnEdge()) {
    return;
  }

  const closed = drawingMode.value !== 'street-axis'
    && (startedFromExistingPolygon.value || perimeterPoints.value.length >= 3);

  const snapped = applyDrawingSnap(lat, lng, {
    includeDrawingPoints: !startedFromExistingPolygon.value,
    includeDrawingSegments: !startedFromExistingPolygon.value,
  });

  const toleranceMeters = resolveSnapToleranceMeters(map, snapped.lat, snapped.lng, {
    pixelRadius: MAP_SEGMENT_SNAP_PIXEL_RADIUS,
  });

  const nearestEdge = findNearestPolygonEdgeInsert(
    snapped.lat,
    snapped.lng,
    perimeterPoints.value,
    { closed, toleranceMeters },
  );

  if (!nearestEdge) {
    toast.info('Clique mais perto de uma aresta para adicionar um ponto.');
    return;
  }

  insertVertexAtIndex(nearestEdge.insertIndex, nearestEdge.lat, nearestEdge.lng);
}

function insertVertexAtIndex(insertIndex, lat, lng) {
  if (!drawingMode.value) {
    return;
  }

  perimeterPoints.value.splice(insertIndex, 0, [lat, lng]);

  const minimumPoints = getMinimumPolygonPoints(drawingMode.value);
  if (drawingMode.value !== 'street-axis' && perimeterPoints.value.length >= minimumPoints) {
    startedFromExistingPolygon.value = true;
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

  refreshTempPolyline(startedFromExistingPolygon.value && perimeterPoints.value.length >= 3);
  refreshVertexMarkerStyles();
  bringVertexMarkersToFront();
  syncDrawingCursorPreview();

  if (drawingMode.value === 'street-axis') {
    updateAxisPreviewFromPoints();
  }

  if (drawingMode.value === 'zone' && !canPlaceZonePoint([lat, lng])) {
    toast.warning('Vértice fora do perímetro do empreendimento.');
  }
}

function removeVertexAtIndex(index) {
  if (!drawingMode.value || index < 0 || index >= perimeterPoints.value.length) {
    return;
  }

  const minPoints = drawingMode.value === 'street-axis' ? 2 : 1;
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
    refreshTempPolyline(startedFromExistingPolygon.value && perimeterPoints.value.length >= 3);
  } else {
    clearEdgeLabelMarkers();
  }

  if (drawingMode.value === 'street-axis') {
    updateAxisPreviewFromPoints();
  }

  refreshVertexMarkerStyles();
  bringVertexMarkersToFront();
  syncDrawingCursorPreview();
}

function undoLastPoint() {
  if (rectangleAnchor.value) {
    clearRectangleDrawingState();
    syncDrawingCursorPreview();
    return;
  }

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
    refreshTempPolyline(startedFromExistingPolygon.value && perimeterPoints.value.length >= 3);
  } else {
    clearEdgeLabelMarkers();
  }

  if (drawingMode.value === 'street-axis') {
    updateAxisPreviewFromPoints();
  }

  syncDrawingCursorPreview();
}

function handleMapInteractionEscape(event) {
  if (event.key !== 'Escape') {
    return;
  }

  if (measureMode.value) {
    event.preventDefault();
    event.stopImmediatePropagation();

    if (measurePoints.value.length > 0) {
      undoMeasurePoint();
    } else {
      stopMeasureMode({ silent: true });
    }
    return;
  }

  if (drawingMode.value) {
    event.preventDefault();
    event.stopImmediatePropagation();

    if (rectangleAnchor.value) {
      clearRectangleDrawingState();
      syncDrawingCursorPreview();
      return;
    }

    if (perimeterPoints.value.length > 0) {
      undoLastPoint();
    } else {
      cancelDrawing();
    }
  }
}

function onMapClick(e) {
  if (measureMode.value) {
    handleMeasureMapClick(e);
    return;
  }

  if (!drawingMode.value || !L) return;

  const snapped = applyDrawingSnap(e.latlng.lat, e.latlng.lng);
  const lat = snapped.lat;
  const lng = snapped.lng;
  const clickLatLng = L.latLng(lat, lng);

  if (
    (drawingMode.value === 'zone' || drawingMode.value === 'perimeter')
    && drawingShapeMode.value === 'rectangle'
    && !startedFromExistingPolygon.value
    && perimeterPoints.value.length === 0
  ) {
    handleRectangleMapClick(lat, lng);
    return;
  }

  const minPointsToClose = getMinimumPointsToClose(drawingMode.value);

  perimeterPoints.value.push([lat, lng]);

  const markerColor = drawingMode.value === 'perimeter'
    ? getPerimeterColor()
    : drawingMode.value === 'street-axis'
      ? getStreetColor(drawingStreet.value)
      : drawingZone.value?.color ?? '#10B981';

  addDrawingMarker([lat, lng], markerColor, perimeterPoints.value.length - 1);

  if (
    drawingMode.value !== 'street-axis'
    && perimeterPoints.value.length >= minPointsToClose
    && isNearFirst(clickLatLng)
  ) {
    finishDrawing({ closedExplicitly: true });
    return;
  }

  refreshTempPolyline(startedFromExistingPolygon.value && perimeterPoints.value.length >= 3);
  refreshVertexMarkerStyles();
  bringVertexMarkersToFront();
  syncDrawingCursorPreview();

  if (drawingMode.value === 'street-axis') {
    updateAxisPreviewFromPoints();
  }

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

  const isPolygonDrawing = drawingMode.value === 'street-axis'
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
  removeTempShapeHitLayer();
  if (map._tempClosingLine) {
    map.removeLayer(map._tempClosingLine);
    delete map._tempClosingLine;
  }

  const lineColor = drawingMode.value === 'perimeter'
    ? getPerimeterColor()
    : drawingMode.value === 'street-axis'
      ? getStreetColor(drawingStreet.value)
      : drawingZone.value?.color ?? '#10B981';
  const zoneInvalid = drawingMode.value === 'zone'
    && getDevelopmentPerimeter()
    && getInvalidPointsInsidePolygon(perimeterPoints.value, getDevelopmentPerimeter()).length > 0;
  const strokeColor = zoneInvalid ? '#DC2626' : lineColor;
  const edgeInsertEnabled = canInsertVerticesOnEdge();

  const layerOptions = {
    color: strokeColor,
    weight: drawingMode.value === 'street-axis' ? 1 : 2,
    dashArray: drawingMode.value === 'street-axis' ? '5 4' : '4',
    opacity: drawingMode.value === 'street-axis' ? 0.9 : 1,
    interactive: false,
  };

  const shouldRenderClosed = drawingMode.value === 'street-axis'
    ? false
    : perimeterPoints.value.length >= 3 && (startedFromExistingPolygon.value || closed);

  if (shouldRenderClosed) {
    map._tempLine = L.polygon(perimeterPoints.value, {
      ...layerOptions,
      fillColor: strokeColor,
      fillOpacity: 0.12,
    }).addTo(map);
  } else {
    map._tempLine = L.polyline(perimeterPoints.value, layerOptions).addTo(map);
  }

  if (edgeInsertEnabled) {
    addTempShapeHitLayer(
      perimeterPoints.value,
      shouldRenderClosed,
    );
  }

  if (livePreview) {
    if (drawingMode.value === 'street-axis') {
      updateAxisPreviewFromPoints();
    }
    return;
  }

  refreshEdgeLabels();
  refreshVertexMarkerStyles();
  bringVertexMarkersToFront();
  bringEdgeLabelMarkersToFront();

  if (drawingMode.value === 'street-axis') {
    updateAxisPreviewFromPoints();
  }
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

  if (drawingMode.value === 'street-axis' && perimeterPoints.value.length < 2) {
    toast.warning('Trace ao menos 2 pontos para o eixo da rua.');
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
  resetDrawingShapeMode();
  resetMapCursor();
  perimeterPoints.value = [];
  startedFromExistingPolygon.value = false;
  drawingMode.value = null;
  drawingZone.value = null;
  drawingStreet.value = null;
  syncMapOverlayInteraction();
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

  if (mode === 'street-axis' && savedStreet) {
    teardownStreetAxisDrawing();
    await finalizeStreetAxis(savedStreet, savedCoords);
  }
}

function removeTempShapeHitLayer() {
  if (map?._tempLineHit) {
    map.removeLayer(map._tempLineHit);
    delete map._tempLineHit;
  }
}

function addTempShapeHitLayer(coords, closed) {
  if (!map || !L || !coords?.length) {
    return;
  }

  removeTempShapeHitLayer();

  const hitOptions = {
    color: '#000000',
    weight: 16,
    opacity: 0,
    fillColor: '#000000',
    fillOpacity: 0.001,
    interactive: true,
    className: 'map-temp-shape-hit',
  };

  if (closed && coords.length >= 3) {
    map._tempLineHit = L.polygon(coords, hitOptions).addTo(map);
  } else {
    map._tempLineHit = L.polyline(coords, hitOptions).addTo(map);
  }

  bindTempShapeEdgeHandlers(map._tempLineHit);
  map._tempLineHit.bringToFront();
}

function clearTempLayers() {
  tempMarkers.forEach((m) => map?.removeLayer(m));
  tempMarkers = [];
  clearEdgeLabelMarkers();
  clearRectangleDrawingState();
  if (map?._tempLine) {
    map.removeLayer(map._tempLine);
    delete map._tempLine;
  }
  removeTempShapeHitLayer();
  if (map?._tempClosingLine) {
    map.removeLayer(map._tempClosingLine);
    delete map._tempClosingLine;
  }

  cursorPreview.clear();
  clearSnapHintMarkers();
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
    buildPerimeterPopupHtml(coords),
    {
      onEdit: () => startDrawPerimeter(),
      onClear: () => confirmClearPerimeter(),
    },
  );

  bringZoneLayersToFront();
  map.fitBounds(perimeterLayer.getBounds(), { padding: [20, 20] });
  syncMapLayerVisibility();
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
        onGenerateLots: () => openGenerateLots(zone, { preferGeometric: true }),
        onClear: () => confirmClearZone(zone),
      },
    );

    zoneLayers[zone.id] = layer;
  });

  bringZoneLayersToFront();
  bringLotLayersToFront();
  syncMapLayerVisibility();
}

function startDrawPerimeter() {
  if (drawingMode.value || measureMode.value) {
    cancelDrawing();
    stopMeasureMode({ discardInProgress: true, silent: true });
  }

  clearTempLayers();
  prepareMapForVertexEditing();
  drawingMode.value = 'perimeter';
  drawingZone.value = null;
  showZoneMapPicker.value = false;
  map?.closePopup();
  syncMapOverlayInteraction();

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
    resetDrawingShapeMode();
  }

  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  syncDrawingCursorPreview();
}

function startDrawZone(zone) {
  if (drawingMode.value || measureMode.value) {
    cancelDrawing();
    stopMeasureMode({ discardInProgress: true, silent: true });
  }

  clearTempLayers();
  prepareMapForVertexEditing();
  drawingMode.value = 'zone';
  drawingZone.value = zone;
  showZoneMapPicker.value = false;
  map?.closePopup();
  syncMapOverlayInteraction();

  if (zone.coordinates?.length >= 3) {
    if (zone.id && zoneLayers[zone.id]) {
      map?.removeLayer(zoneLayers[zone.id]);
      delete zoneLayers[zone.id];
    }
    preloadDrawingPoints(zone.coordinates, zone.color ?? '#10B981');
    toast.info(`Área de "${zone.name}" carregada. Arraste vértices; duplo clique na linha adiciona ponto.`);
  } else {
    perimeterPoints.value = [];
    startedFromExistingPolygon.value = false;
    resetDrawingShapeMode();
    toast.info(`Desenhando área de "${zone.name}". Clique para marcar; duplo clique na linha adiciona ponto.`);
  }

  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  syncDrawingCursorPreview();
  focusMapForDrawing();

  if (zone.coordinates?.length >= 3) {
    try {
      map.fitBounds(L.polygon(zone.coordinates).getBounds(), { padding: [48, 48], maxZoom: 19 });
    } catch {
      /* geometria inválida */
    }
  }
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
}

function openNewZoneFromMapPicker() {
  showZoneMapPicker.value = false;
  openZoneForm();
}

function cancelDrawing() {
  clearFirstVertexCloseTimer();
  cursorPreview.unbind();
  clearTempLayers();
  resetDrawingShapeMode();
  teardownStreetAxisDrawing();
  resetMapCursor();
  perimeterPoints.value = [];
  startedFromExistingPolygon.value = false;
  drawingMode.value = null;
  drawingZone.value = null;
  drawingStreet.value = null;
  showZoneMapPicker.value = false;
  syncMapOverlayInteraction();
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
    syncMapOverlayInteraction();
    drawZonesOnMap();
  } catch {
    toast.error('Erro ao salvar área da zona.');
    await loadZones();
    syncMapOverlayInteraction();
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
const streetsSectionExpanded = ref(false);
const zonesSectionExpanded = ref(false);
const streets = ref([]);
const lots = ref([]);
const visibleZoneNameTypes = ref([]);
const showZoneNamePicker = ref(false);
const zoneNamePickerDraft = ref([]);
const visibleMapLayers = ref([...ALL_MAP_LAYER_IDS]);
const showMapLayerPicker = ref(false);
const mapLayerPickerDraft = ref([]);
const mapLayerOptions = MAP_LAYER_OPTIONS;
const hasCustomMapLayerSelection = computed(() =>
  visibleMapLayers.value.length !== ALL_MAP_LAYER_IDS.length,
);
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

const zoneTypeOptions = ZONE_TYPE_OPTIONS;

const zoneForm = reactive({ name: '', type: 'quadra', color: '#3B82F6', parent_zone_id: '', price_per_m2: 0 });
const zoneFormErrors = reactive({ name: '', type: '' });

watch(
  () => zoneForm.type,
  () => {
    const isValidParent = parentZoneOptions.value.some(
      (option) => option.value === zoneForm.parent_zone_id,
    );

    if (!isValidParent) {
      zoneForm.parent_zone_id = '';
    }
  },
);
const streetForm = ref({ name: '', color: defaultStreetColor, width: 10, end_cap: 'round' });

const streetEndCapOptions = [
  { value: 'round', label: 'Extremidades arredondadas' },
  { value: 'square', label: 'Extremidades quadradas' },
];

const streetWidthPresetOptions = [
  { value: 5, label: '5 m — rua estreita' },
  { value: 6, label: '6 m' },
  { value: 7, label: '7 m' },
  { value: 8, label: '8 m' },
  { value: 10, label: '10 m — padrão' },
  { value: 12, label: '12 m' },
  { value: 15, label: '15 m' },
  { value: 18, label: '18 m' },
  { value: 20, label: '20 m' },
  { value: 25, label: '25 m' },
  { value: 30, label: '30 m — via larga' },
];

function buildStreetWidthSelectOptions(currentWidth) {
  const presetValues = streetWidthPresetOptions.map((option) => option.value);
  const normalizedWidth = Number(currentWidth) || 10;
  const options = presetValues.includes(normalizedWidth)
    ? streetWidthPresetOptions
    : [
        { value: normalizedWidth, label: `${normalizedWidth} m — largura atual` },
        ...streetWidthPresetOptions,
      ];

  return options
    .map((option) => {
      const selected = option.value === normalizedWidth ? ' selected' : '';
      return `<option value="${option.value}"${selected}>${escapeHtml(option.label)}</option>`;
    })
    .join('');
}

async function promptStreetAxisWidth(street) {
  const currentWidth = Number(street.width) || 10;
  const currentEndCap = normalizeStreetEndCap(street.end_cap);
  const optionsHtml = buildStreetWidthSelectOptions(currentWidth);
  const endCapOptionsHtml = streetEndCapOptions
    .map((option) => {
      const selected = option.value === currentEndCap ? ' selected' : '';
      return `<option value="${option.value}"${selected}>${escapeHtml(option.label)}</option>`;
    })
    .join('');

  const result = await Swal.fire({
    ...swalDefaultConfig,
    title: 'Largura da rua',
    html: `
      <p class="mb-3 text-sm text-slate-600">
        Escolha a largura da faixa para traçar o eixo de
        <strong>${escapeHtml(street.name)}</strong>.
      </p>
      <label for="street-width-select" class="mb-1 block text-left text-xs font-medium text-slate-600">
        Largura (m)
      </label>
      <select
        id="street-width-select"
        class="mb-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
      >
        ${optionsHtml}
      </select>
      <label for="street-end-cap-select" class="mb-1 block text-left text-xs font-medium text-slate-600">
        Extremidades do eixo
      </label>
      <select
        id="street-end-cap-select"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"
      >
        ${endCapOptionsHtml}
      </select>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Traçar eixo',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    focusConfirm: false,
    didOpen: () => {
      document.getElementById('street-width-select')?.focus();
    },
    preConfirm: () => {
      const select = document.getElementById('street-width-select');
      const endCapSelect = document.getElementById('street-end-cap-select');
      const width = Number(select?.value);

      if (!(width > 0)) {
        Swal.showValidationMessage('Selecione uma largura válida.');
        return false;
      }

      return {
        width,
        endCap: normalizeStreetEndCap(endCapSelect?.value),
      };
    },
  });

  return result.isConfirmed ? result.value : null;
}

function clearAxisPreviewLayer() {
  if (axisPreviewLayer && map) {
    map.removeLayer(axisPreviewLayer);
    axisPreviewLayer = null;
  }
}

function updateAxisPreviewFromPoints() {
  const street = drawingStreet.value || axisStreet.value;
  if (!street || !map || !L) return;

  axisPreviewLength.value = centerlineLengthMeters(perimeterPoints.value);
  clearAxisPreviewLayer();

  if (perimeterPoints.value.length < 2) return;

  const polygon = buildStreetPolygon(
    perimeterPoints.value,
    Number(street.width) || 10,
    street.end_cap,
  );
  if (!polygon) return;

  axisPreviewLayer = L.polygon(polygon, {
    ...getStreetLayerStyle(street, { preview: true }),
    interactive: false,
    className: 'map-street-axis-preview',
  }).addTo(map);
}

function onMapDblClickFinishAxis(event) {
  if (drawingMode.value !== 'street-axis') return;
  L?.DomEvent.stopPropagation(event);
  if (perimeterPoints.value.length >= 2) {
    finishDrawing();
  }
}

function teardownStreetAxisDrawing() {
  map?.off('dblclick', onMapDblClickFinishAxis);
  map?.doubleClickZoom?.enable();
  clearAxisPreviewLayer();
  axisStreet.value = null;
  axisPreviewLength.value = 0;
}

function buildStreetFormPayload() {
  const payload = {
    name: streetForm.value.name.trim(),
    color: streetForm.value.color,
    width: Number(streetForm.value.width) || 10,
    end_cap: normalizeStreetEndCap(streetForm.value.end_cap),
  };

  if (editingStreet.value?.centerline?.length >= 2) {
    const polygon = buildStreetPolygon(
      editingStreet.value.centerline,
      payload.width,
      payload.end_cap,
    );
    if (polygon) {
      payload.centerline = editingStreet.value.centerline;
      payload.coordinates = polygon;
    }
  }

  return payload;
}

const parentZoneOptions = computed(() =>
  getValidParentZones(zones.value, zoneForm.type, editingZone.value?.id ?? null)
    .sort((a, b) => {
      const rankDiff = getZoneTypeRank(a.type) - getZoneTypeRank(b.type);
      if (rankDiff !== 0) {
        return rankDiff;
      }

      return String(a.name).localeCompare(String(b.name), 'pt-BR', { sensitivity: 'base', numeric: true });
    })
    .map((zone) => ({
      value: String(zone.id),
      label: `${buildZoneTitleLabel(zone)} (${zoneTypeLabel(zone.type)})`,
    })),
);

const hierarchicalZones = computed(() => buildZoneHierarchyList(zones.value));

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
    const items = Array.isArray(data) ? data : data.data ?? [];

    zones.value = items.map((zone) => ({
      ...zone,
      coordinates: normalizePolygonCoordinates(zone.coordinates) ?? [],
    }));
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
  syncMapLayerVisibility();
}

function getStreetUnionVisualStyle(mappedStreets) {
  if (mappedStreets.length === 1) {
    return getStreetLayerStyle(mappedStreets[0]);
  }

  const color = defaultStreetColor;

  return {
    color,
    weight: 2,
    fillColor: lightenHexColor(color),
    fillOpacity: 0.42,
    opacity: 0.95,
  };
}

function drawStreetsOnMap(options = {}) {
  if (!L || !map) return;

  const excludeStreetId = options.excludeStreetId ?? null;

  if (streetUnionVisualLayer) {
    map.removeLayer(streetUnionVisualLayer);
    streetUnionVisualLayer = null;
  }

  Object.values(streetLayersMap).forEach((layer) => map.removeLayer(layer));
  streetLayersMap = {};

  const mappedStreets = streets.value.filter(
    (street) => hasValidStreetPolygon(street.coordinates?.length ?? 0)
      && String(street.id) !== String(excludeStreetId),
  );

  if (!mappedStreets.length) {
    syncMapLayerVisibility();
    return;
  }

  const useUnionVisual = mappedStreets.length > 1;
  let mergedRings = useUnionVisual
    ? buildStreetNetworkVisualRings(mappedStreets)
    : [];
  const renderUnionVisual = useUnionVisual && mergedRings.length > 0;

  if (renderUnionVisual) {
    streetUnionVisualLayer = L.layerGroup();

    mergedRings.forEach((ring) => {
      L.polygon(ring, {
        ...getStreetUnionVisualStyle(mappedStreets),
        interactive: false,
        className: 'map-street-union-visual',
      }).addTo(streetUnionVisualLayer);
    });

    streetUnionVisualLayer.addTo(map);
  }

  mappedStreets.forEach((street) => {
    const layer = L.polygon(street.coordinates, renderUnionVisual
      ? {
        color: getStreetColor(street),
        weight: 0,
        opacity: 0,
        fillColor: getStreetColor(street),
        fillOpacity: 0.001,
        className: 'map-feature-polygon map-street-feature map-street-feature-hit',
      }
      : {
        ...getStreetLayerStyle(street),
        className: 'map-feature-polygon map-street-feature',
      })
      .bindTooltip(street.name, { sticky: true })
      .addTo(map);

    resetMapFeatureLayerInteraction(layer);

    bindMapFeaturePopup(
      layer,
      buildStreetPopupHtml(street),
      {
        onEdit: () => startDrawStreetAxis(street),
        onClear: () => confirmClearStreet(street),
      },
    );

    streetLayersMap[street.id] = layer;
  });

  syncMapLayerVisibility();
}

function openStreetForm() {
  editingStreet.value = null;
  streetForm.value = { name: '', color: defaultStreetColor, width: 10, end_cap: 'round' };
  showStreetForm.value = true;
}

function editStreet(street) {
  editingStreet.value = street;
  streetForm.value = {
    name: street.name,
    color: street.color || defaultStreetColor,
    width: street.width ?? 10,
    end_cap: normalizeStreetEndCap(street.end_cap),
  };
  showStreetForm.value = true;
}

function closeStreetForm() {
  showStreetForm.value = false;
  editingStreet.value = null;
  streetForm.value = { name: '', color: defaultStreetColor, width: 10, end_cap: 'round' };
}

async function recalcStreetWidthFromForm() {
  const street = editingStreet.value;
  if (!street?.centerline?.length) {
    toast.info('Esta rua não tem eixo traçado. Use "Traçar eixo" primeiro.');
    return;
  }

  const width = Number(streetForm.value.width) || 10;
  const endCap = normalizeStreetEndCap(streetForm.value.end_cap);
  const polygon = buildStreetPolygon(street.centerline, width, endCap);
  if (!polygon) {
    toast.error('Não foi possível recalcular a faixa da rua.');
    return;
  }

  savingStreet.value = true;

  try {
    await api.post(`/developments/${route.params.id}/streets/${street.id}/update`, {
      name: streetForm.value.name.trim(),
      color: streetForm.value.color,
      width,
      end_cap: endCap,
      centerline: street.centerline,
      coordinates: polygon,
      order: street.order != null ? Number(street.order) : null,
    });
    toast.success('Largura da rua atualizada.');
    closeStreetForm();
    await loadStreets();
    drawStreetsOnMap();
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Erro ao recalcular largura da rua.');
  } finally {
    savingStreet.value = false;
  }
}

async function saveStreet() {
  if (!streetForm.value.name.trim()) {
    toast.warning('Informe o nome da rua.');
    return;
  }

  savingStreet.value = true;

  try {
    const payload = buildStreetFormPayload();

    if (editingStreet.value) {
      await api.post(
        `/developments/${route.params.id}/streets/${editingStreet.value.id}/update`,
        payload,
      );
      toast.success('Rua atualizada.');
    } else {
      await api.post(`/developments/${route.params.id}/streets`, payload);
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

  if (drawingMode.value === 'street-axis' && drawingStreet.value?.id === street.id) {
    cancelDrawing();
  }

  try {
    await api.post(`/developments/${route.params.id}/streets/${street.id}/update`, {
      name: street.name,
      color: street.color,
      order: street.order,
      coordinates: null,
      centerline: null,
    });
    toast.success('Traçado da rua removido.');
    await loadStreets();
    drawStreetsOnMap();
  } catch {
    toast.error('Erro ao limpar traçado da rua.');
  }
}

async function focusMapForDrawing() {
  scrollMapIntoView();

  if (!isMapFullscreen.value) {
    isMapFullscreen.value = true;
  }

  await nextTick();
  refreshMapLayout();
}

function clearMeasureTempLayers() {
  if (measureTempLayerGroup && map) {
    map.removeLayer(measureTempLayerGroup);
    measureTempLayerGroup = null;
  }
}

function buildMeasureLayerGroup(points, { showTotal = true } = {}) {
  const group = L.featureGroup();
  const line = L.polyline(points, {
    color: '#7c3aed',
    weight: 2.5,
    dashArray: '8 4',
    interactive: false,
    className: 'map-measure-path',
  });
  group.addLayer(line);

  points.forEach((coord) => {
    group.addLayer(L.circleMarker(coord, {
      radius: 5,
      color: '#5b21b6',
      fillColor: '#7c3aed',
      fillOpacity: 0.9,
      weight: 2,
      interactive: false,
    }));
  });

  const edges = getPolygonEdgesMeters(points, { closed: false });
  edges.forEach((edge) => {
    group.addLayer(L.marker(edge.midpoint, {
      interactive: false,
      icon: L.divIcon({
        className: 'map-measure-label-icon',
        html: `<span class="map-measure-label">${edge.lengthLabel}</span>`,
        iconSize: [0, 0],
      }),
    }));
  });

  if (showTotal && edges.length) {
    const totalMeters = edges.reduce((sum, edge) => sum + edge.lengthMeters, 0);
    const lastPoint = points[points.length - 1];
    group.addLayer(L.marker(lastPoint, {
      interactive: false,
      zIndexOffset: 1300,
      icon: L.divIcon({
        className: 'map-measure-total-icon',
        html: `<span class="map-measure-total">Total: ${formatMeters(totalMeters)}</span>`,
        iconSize: [0, 0],
      }),
    }));
  }

  return group;
}

function refreshMeasurePreview() {
  if (!map || !L) return;

  clearMeasureTempLayers();

  if (measurePoints.value.length < 2) {
    if (measurePoints.value.length === 1) {
      measureTempLayerGroup = L.featureGroup().addTo(map);
      measureTempLayerGroup.addLayer(L.circleMarker(measurePoints.value[0], {
        radius: 5,
        color: '#5b21b6',
        fillColor: '#7c3aed',
        fillOpacity: 0.9,
        weight: 2,
        interactive: false,
      }));
    }
    return;
  }

  measureTempLayerGroup = buildMeasureLayerGroup(measurePoints.value);
  measureTempLayerGroup.addTo(map);
  measureTempLayerGroup.bringToFront?.();
}

function handleMeasureMapClick(event) {
  if (!L) return;

  const { lat, lng } = event.latlng;
  measurePoints.value.push([lat, lng]);
  refreshMeasurePreview();
  syncMeasureCursorPreview();
}

function undoMeasurePoint() {
  if (!measurePoints.value.length) return;
  measurePoints.value.pop();
  refreshMeasurePreview();
  syncMeasureCursorPreview();
}

function commitCurrentMeasure() {
  if (measurePoints.value.length < 2) {
    toast.warning('Adicione pelo menos 2 pontos para medir.');
    return;
  }

  if (!map || !L) return;

  const points = measurePoints.value.map((point) => [Number(point[0]), Number(point[1])]);
  const group = buildMeasureLayerGroup(points);
  group.addTo(map);
  group.bringToFront?.();
  savedMeasureLayerGroups.push(group);
  savedMeasuresCount.value = savedMeasureLayerGroups.length;

  const edges = getPolygonEdgesMeters(points, { closed: false });
  const totalMeters = edges.reduce((sum, edge) => sum + edge.lengthMeters, 0);

  measurePoints.value = [];
  clearMeasureTempLayers();
  syncMeasureCursorPreview();
  toast.success(`Medição fixada: ${formatMeters(totalMeters)}`);
}

function startMeasureMode() {
  if (drawingMode.value) {
    cancelDrawing();
  }

  measureMode.value = true;
  measurePoints.value = [];
  clearMeasureTempLayers();
  map?.closePopup();
  syncMapOverlayInteraction();
  focusMapForDrawing();
  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  syncMeasureCursorPreview();
}

function stopMeasureMode({ discardInProgress = true, silent = false } = {}) {
  if (discardInProgress) {
    measurePoints.value = [];
    clearMeasureTempLayers();
  }

  measureMode.value = false;
  map?.getContainer()?.style.removeProperty('cursor');
  syncMapOverlayInteraction();
  syncMeasureCursorPreview();

  if (!silent) {
    toast.info('Trena desativada.');
  }
}

function clearAllMeasures() {
  savedMeasureLayerGroups.forEach((group) => {
    map?.removeLayer(group);
  });
  savedMeasureLayerGroups.length = 0;
  savedMeasuresCount.value = 0;
  clearMeasureTempLayers();
  measurePoints.value = [];
  toast.success('Todas as medições foram removidas.');
}

async function startDrawStreetAxis(street) {
  const options = await promptStreetAxisWidth(street);
  if (options == null) {
    return;
  }

  beginStreetAxisDrawing(street, options);
}

function beginStreetAxisDrawing(street, { width, endCap }) {
  street.width = width;
  street.end_cap = endCap;

  if (drawingMode.value) {
    cancelDrawing();
  }

  if (measureMode.value) {
    stopMeasureMode({ discardInProgress: true, silent: true });
  }

  axisStreet.value = street;
  clearTempLayers();
  clearAxisPreviewLayer();
  prepareMapForVertexEditing();
  drawingMode.value = 'street-axis';
  drawingStreet.value = street;
  drawingZone.value = null;
  showZoneMapPicker.value = false;
  map?.closePopup();
  syncMapOverlayInteraction();
  axisPreviewLength.value = 0;

  drawStreetsOnMap({ excludeStreetId: street.id });

  if (street.centerline?.length >= 2) {
    preloadDrawingPoints(street.centerline, getStreetColor(street));
    updateAxisPreviewFromPoints();
    refreshVertexMarkerStyles();
    bringVertexMarkersToFront();
    toast.info(`Eixo de "${street.name}" carregado. Arraste os vértices ou adicione novos pontos.`);
  } else {
    perimeterPoints.value = [];
    startedFromExistingPolygon.value = false;
    toast.info(`Trace o eixo de "${street.name}". Clique para adicionar pontos, duplo clique para concluir.`);
  }

  map?.doubleClickZoom?.disable();
  map?.on('dblclick', onMapDblClickFinishAxis);
  map?.getContainer()?.style.setProperty('cursor', 'crosshair');
  syncDrawingCursorPreview();
  focusMapForDrawing();
}

async function finalizeStreetAxis(street, points) {
  if (!points || points.length < 2) {
    toast.warning('Trace ao menos 2 pontos para o eixo da rua.');
    return;
  }

  const polygon = buildStreetPolygon(points, Number(street.width) || 10, street.end_cap);

  if (!polygon) {
    toast.error('Não foi possível gerar a faixa da rua.');
    return;
  }

  try {
    await api.post(`/developments/${route.params.id}/streets/${street.id}/update`, {
      name: street.name,
      color: street.color,
      width: Number(street.width) || 10,
      end_cap: normalizeStreetEndCap(street.end_cap),
      order: street.order != null ? Number(street.order) : null,
      centerline: points,
      coordinates: polygon,
    });
    toast.success('Rua traçada pelo eixo e salva.');
    await loadStreets();
    drawStreetsOnMap();
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Erro ao salvar rua.');
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
  zoneForm.price_per_m2 = 0;
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
  zoneForm.price_per_m2 = zone.price_per_m2 ?? 0;
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
    price_per_m2: zoneForm.price_per_m2 > 0 ? zoneForm.price_per_m2 : null,
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
const genMode = ref('simple');
const generateForm = ref({
  quantity: 10,
  start_from: 1,
  area: '',
  total_value: 0,
  pattern: '',
});
const geoForm = ref({
  lotWidth: 20,
  lotDepth: 30,
  widthMode: 'equal',
  customWidths: [20, 20],
  remainderSide: 'end',
  frontEdgeIndex: null,
  reverseFrontEdge: false,
  total_value: 0,
  start_from: 1,
  pattern: '',
});
const blockEdges = ref([]);
const previewLots = ref([]);
const previewing = ref(false);
const hoveredEdgeIndex = ref(null);
let geoPreviewTimer = null;

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

const generateLotsEffectivePricePerM2 = computed(() => {
  const zone = generateLotsZone.value;
  if (!zone) {
    return 0;
  }

  return resolveEffectivePricePerM2(zone, form.value.base_price_per_m2);
});

function formatPricePerM2Label(cents) {
  return formatMoneyMaskFromCents(cents);
}

function computeLotValueForZone(zone, areaM2) {
  return computeLotTotalValueFromArea(areaM2, resolveEffectivePricePerM2(zone, form.value.base_price_per_m2));
}

function resolveLotTotalValueForGeneration(zone, areaM2, fallbackTotalValue = 0) {
  const computedValue = computeLotValueForZone(zone, areaM2);
  if (computedValue > 0) {
    return computedValue;
  }

  return fallbackTotalValue > 0 ? fallbackTotalValue : null;
}

function syncSimpleGenerateLotValue() {
  const zone = generateLotsZone.value;
  const area = parseFloat(generateForm.value.area);

  if (!zone || !Number.isFinite(area) || area <= 0) {
    return;
  }

  const value = computeLotValueForZone(zone, area);
  if (value > 0) {
    generateForm.value.total_value = value;
  }
}

function syncGeoGenerateLotValue() {
  const zone = generateLotsZone.value;
  if (!zone || !previewLots.value.length) {
    return;
  }

  const firstArea = previewLots.value[0]?.area;
  const value = computeLotValueForZone(zone, firstArea);
  if (value > 0) {
    geoForm.value.total_value = value;
  }
}

const geoFrontLengthM = computed(() => {
  if (geoForm.value.frontEdgeIndex == null) {
    return 0;
  }

  const edge = blockEdges.value.find((item) => item.index === geoForm.value.frontEdgeIndex);
  return edge?.lengthMeters ?? 0;
});

const geoSlicePlan = computed(() => resolveSliceWidths(geoFrontLengthM.value, {
  widthMode: geoForm.value.widthMode,
  lotWidth: Number(geoForm.value.lotWidth),
  customWidths: geoForm.value.customWidths,
  remainderSide: geoForm.value.remainderSide,
}));

const geoCustomWidthsRemainder = computed(() => {
  if (geoForm.value.widthMode !== 'custom' || !(geoFrontLengthM.value > 0)) {
    return 0;
  }

  const defined = geoForm.value.customWidths
    .map((value) => Number(value))
    .filter((value) => Number.isFinite(value) && value > 0)
    .reduce((sum, value) => sum + value, 0);

  return Math.round(Math.max(0, geoFrontLengthM.value - defined) * 100) / 100;
});

watch(
  () => [
    geoForm.value.lotWidth,
    geoForm.value.lotDepth,
    geoForm.value.widthMode,
    geoForm.value.customWidths,
    geoForm.value.remainderSide,
    geoForm.value.reverseFrontEdge,
  ],
  () => {
    if (genMode.value === 'geometric' && geoForm.value.frontEdgeIndex != null) {
      scheduleGeoPreview();
    }
  },
  { deep: true },
);

watch(
  () => geoForm.value.start_from,
  () => {
    if (genMode.value === 'geometric' && previewLots.value.length) {
      drawPreviewLotsOnMap({ fitView: false });
    }
  },
);

watch(
  () => generateForm.value.area,
  () => {
    if (generateLotsZone.value && genMode.value === 'simple') {
      syncSimpleGenerateLotValue();
    }
  },
);

watch(
  () => streets.value,
  () => {
    if (genMode.value === 'geometric' && generateLotsZone.value) {
      loadBlockEdges();
    }
  },
  { deep: true },
);

function loadBlockEdges() {
  const zone = generateLotsZone.value;
  if (!zone?.coordinates || zone.coordinates.length < 3) {
    blockEdges.value = [];
    return;
  }
  blockEdges.value = enrichBlockEdgesWithStreets(zone.coordinates, streets.value);

  if (geoForm.value.frontEdgeIndex == null) {
    const edgeWithStreet = blockEdges.value.find((edge) => edge.nearestStreet);
    if (edgeWithStreet) {
      geoForm.value.frontEdgeIndex = edgeWithStreet.index;
      scheduleGeoPreview();
    }
  }

  nextTick(() => drawBlockEdgesOnMap());
}

function clearBlockEdgeLayers() {
  if (blockEdgeLayerGroup && map) {
    map.removeLayer(blockEdgeLayerGroup);
    blockEdgeLayerGroup = null;
  }
}

function getBlockEdgeStyle(edge) {
  const isSelected = geoForm.value.frontEdgeIndex === edge.index;
  const isHovered = hoveredEdgeIndex.value === edge.index;
  const active = isSelected || isHovered;

  return {
    color: isSelected ? '#c9a84c' : isHovered ? '#2d6a45' : '#475569',
    weight: active ? 7 : 4,
    opacity: active ? 1 : 0.75,
  };
}

function drawBlockEdgesOnMap() {
  if (!map || !L || genMode.value !== 'geometric' || !blockEdges.value.length) {
    return;
  }

  clearBlockEdgeLayers();
  blockEdgeLayerGroup = L.featureGroup().addTo(map);

  blockEdges.value.forEach((edge) => {
    const style = getBlockEdgeStyle(edge);
    const line = L.polyline([edge.fromCoord, edge.toCoord], {
      ...style,
      interactive: true,
      className: 'map-block-edge-picker',
    }).addTo(blockEdgeLayerGroup);

    line.on('click', (event) => {
      L.DomEvent.stopPropagation(event);
      selectFrontEdge(edge.index);
    });

    line.on('mouseover', () => {
      hoveredEdgeIndex.value = edge.index;
      drawBlockEdgesOnMap();
    });

    line.on('mouseout', () => {
      if (hoveredEdgeIndex.value === edge.index) {
        hoveredEdgeIndex.value = null;
        drawBlockEdgesOnMap();
      }
    });

    const active = geoForm.value.frontEdgeIndex === edge.index || hoveredEdgeIndex.value === edge.index;
    const label = L.marker(edge.midpoint, {
      interactive: true,
      zIndexOffset: 1500,
      icon: L.divIcon({
        className: 'map-block-edge-label-icon',
        html: `<span class="map-block-edge-label${active ? ' map-block-edge-label--active' : ''}">${edge.index + 1}</span>`,
        iconSize: [0, 0],
      }),
    }).addTo(blockEdgeLayerGroup);

    label.on('click', (event) => {
      L.DomEvent.stopPropagation(event);
      selectFrontEdge(edge.index);
    });
  });

  if (blockEdgeLayerGroup) {
    blockEdgeLayerGroup.bringToFront();
  }
}

function selectFrontEdge(index) {
  geoForm.value.frontEdgeIndex = index;
  hoveredEdgeIndex.value = null;
  if (geoForm.value.widthMode === 'custom' && geoFrontLengthM.value > 0) {
    seedCustomWidthsIfNeeded();
  }
  drawBlockEdgesOnMap();
  scheduleGeoPreview();
}

function setGeoWidthMode(mode) {
  if (geoForm.value.widthMode === mode) {
    return;
  }

  geoForm.value.widthMode = mode;

  if (mode === 'custom') {
    fillCustomWidthsFromEqual();
  }

  if (geoForm.value.frontEdgeIndex != null) {
    scheduleGeoPreview();
  }
}

function setGeoRemainderSide(side) {
  if (geoForm.value.remainderSide === side) {
    return;
  }

  geoForm.value.remainderSide = side;

  if (geoForm.value.frontEdgeIndex != null) {
    scheduleGeoPreview();
  }
}

function seedCustomWidthsIfNeeded() {
  const hasValidWidth = geoForm.value.customWidths.some(
    (value) => Number.isFinite(Number(value)) && Number(value) > 0,
  );

  if (hasValidWidth) {
    return;
  }

  fillCustomWidthsFromEqual();
}

function fillCustomWidthsFromEqual() {
  const frontLength = geoFrontLengthM.value;
  const lotCount = Math.max(1, geoForm.value.customWidths.length);

  if (!(frontLength > 0)) {
    const lotWidth = Number(geoForm.value.lotWidth) || 20;
    geoForm.value.customWidths = divideFrontLengthEqually(lotWidth * lotCount, lotCount);
    return;
  }

  geoForm.value.customWidths = divideFrontLengthEqually(frontLength, lotCount);

  if (geoForm.value.frontEdgeIndex != null) {
    scheduleGeoPreview();
  }
}

function splitCustomWidthsHalfHalf() {
  if (!(geoFrontLengthM.value > 0)) {
    return;
  }

  const half = Math.round((geoFrontLengthM.value / 2) * 100) / 100;
  const other = Math.round((geoFrontLengthM.value - half) * 100) / 100;
  geoForm.value.customWidths = [half, other];
  geoForm.value.remainderSide = 'end';

  if (geoForm.value.frontEdgeIndex != null) {
    scheduleGeoPreview();
  }
}

function addCustomWidthRow() {
  const fallback = Number(geoForm.value.lotWidth) || 20;
  const last = geoForm.value.customWidths[geoForm.value.customWidths.length - 1];
  geoForm.value.customWidths.push(Number(last) > 0 ? Number(last) : fallback);
}

function removeCustomWidthRow(index) {
  if (geoForm.value.customWidths.length <= 1) {
    return;
  }

  geoForm.value.customWidths.splice(index, 1);

  if (geoForm.value.frontEdgeIndex != null) {
    scheduleGeoPreview();
  }
}

function toggleGeoReverseFrontEdge() {
  geoForm.value.reverseFrontEdge = !geoForm.value.reverseFrontEdge;
  if (geoForm.value.frontEdgeIndex != null) {
    if (geoPreviewTimer) {
      clearTimeout(geoPreviewTimer);
      geoPreviewTimer = null;
    }
    buildPreview({ silent: true });
  }
}

function hoverFrontEdge(index) {
  hoveredEdgeIndex.value = index;
  drawBlockEdgesOnMap();
}

function fitMapToGenerateLotsZone() {
  const coords = generateLotsZone.value?.coordinates;
  if (!map || !L || !coords?.length) {
    return;
  }

  try {
    map.fitBounds(L.polygon(coords).getBounds(), { padding: [80, 80], maxZoom: 19 });
  } catch {
    /* geometria inválida */
  }
}

function scrollMapIntoView() {
  nextTick(() => {
    mapSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
}

function scheduleGeoPreview() {
  if (geoPreviewTimer) {
    clearTimeout(geoPreviewTimer);
  }

  geoPreviewTimer = setTimeout(() => {
    geoPreviewTimer = null;
    if (genMode.value === 'geometric' && geoForm.value.frontEdgeIndex != null) {
      buildPreview({ silent: true });
    }
  }, 350);
}

function clearPreviewLayer() {
  if (previewLayerGroup && map) {
    map.removeLayer(previewLayerGroup);
    previewLayerGroup = null;
  }
}

function drawPreviewLotsOnMap({ fitView = false } = {}) {
  if (!map || !L) return;

  clearPreviewLayer();
  previewLayerGroup = L.featureGroup().addTo(map);

  previewLots.value.forEach((lot, i) => {
    const lotNumber = (parseInt(geoForm.value.start_from, 10) || 1) + i;

    L.polygon(lot.coordinates, {
      color: lot.clipped ? '#f59e0b' : '#c9a84c',
      weight: 2,
      fillColor: lot.clipped ? '#f59e0b' : '#c9a84c',
      fillOpacity: 0.25,
      dashArray: '6 4',
      className: 'map-lot-preview-path',
    })
      .bindTooltip(
        `Lote ${lotNumber} · ${lot.widthMeters}m × ${lot.depthMeters}m · ${lot.area}m²`,
        { permanent: false },
      )
      .addTo(previewLayerGroup);
  });

  if (fitView) {
    try {
      const bounds = previewLayerGroup.getBounds();
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [80, 80], maxZoom: 20 });
      }
    } catch {
      /* geometria inválida */
    }
  }

  drawBlockEdgesOnMap();
}

function buildPreview({ silent = false } = {}) {
  const zone = generateLotsZone.value;
  if (!zone?.coordinates || geoForm.value.frontEdgeIndex == null) {
    if (!silent) {
      toast.warning('Selecione o lado da quadra que dá para a rua.');
    }
    return;
  }
  previewing.value = true;
  try {
    previewLots.value = subdivideBlockIntoLots({
      blockLatLng: zone.coordinates,
      frontEdgeIndex: geoForm.value.frontEdgeIndex,
      lotWidth: Number(geoForm.value.lotWidth),
      lotDepth: Number(geoForm.value.lotDepth),
      widthMode: geoForm.value.widthMode,
      customWidths: geoForm.value.customWidths,
      remainderSide: geoForm.value.remainderSide,
      reverseFrontEdge: geoForm.value.reverseFrontEdge,
    });
    if (!previewLots.value.length && !silent) {
      toast.warning('Nenhum lote gerado. Verifique as dimensões e o lado selecionado.');
    }
    syncGeoGenerateLotValue();
    drawPreviewLotsOnMap({ fitView: false });
  } finally {
    previewing.value = false;
  }
}

function switchGenMode(mode) {
  if (genMode.value === mode) return;
  clearPreviewLayer();
  previewLots.value = [];
  geoForm.value.frontEdgeIndex = null;
  hoveredEdgeIndex.value = null;
  genMode.value = mode;

  if (mode === 'geometric') {
    loadBlockEdges();
    fitMapToGenerateLotsZone();
    scrollMapIntoView();
  } else {
    clearBlockEdgeLayers();
  }
}

function closeGenerateLotsModal() {
  if (geoPreviewTimer) {
    clearTimeout(geoPreviewTimer);
    geoPreviewTimer = null;
  }
  clearPreviewLayer();
  clearBlockEdgeLayers();
  previewLots.value = [];
  hoveredEdgeIndex.value = null;
  genMode.value = 'simple';
  generateLotsZone.value = null;
}

function openGenerateLots(zone, { preferGeometric = false } = {}) {
  if (!canGenerateLotsInZone(zone)) {
    toast.warning(generateLotsBlockedReason(zone));
    return;
  }

  map?.closePopup();

  generateLotsZone.value = zone;
  genMode.value = preferGeometric ? 'geometric' : 'simple';
  previewLots.value = [];
  blockEdges.value = [];
  generateForm.value = {
    quantity: 10,
    start_from: 1,
    area: '',
    total_value: 0,
    pattern: '',
  };
  geoForm.value = {
    lotWidth: 20,
    lotDepth: 30,
    widthMode: 'equal',
    customWidths: [20, 20],
    remainderSide: 'end',
    frontEdgeIndex: null,
    reverseFrontEdge: false,
    total_value: 0,
    start_from: 1,
    pattern: '',
  };

  if (preferGeometric) {
    nextTick(() => {
      loadBlockEdges();
      fitMapToGenerateLotsZone();
      focusMapForDrawing();
    });
  }
}

async function doGenerateGeometricLots() {
  if (generating.value) {
    return;
  }

  if (!previewLots.value.length) {
    toast.warning('Gere o preview antes de salvar.');
    return;
  }

  if (!generateLotsZone.value?.id) {
    toast.error('Zona não encontrada. Feche e abra o painel novamente.');
    return;
  }

  if (!route.params.id) {
    toast.error('Salve o empreendimento antes de gerar lotes.');
    return;
  }

  generating.value = true;
  try {
    const { data } = await api.post(
      `/developments/${route.params.id}/zones/${generateLotsZone.value.id}/generate-lots-geometric`,
      {
        start_from: parseInt(geoForm.value.start_from, 10) || 1,
        total_value: geoForm.value.total_value || null,
        pattern: geoForm.value.pattern || null,
        lot_width: Number(geoForm.value.lotWidth),
        lot_depth: Number(geoForm.value.lotDepth),
        lots: previewLots.value.map((l) => ({
          coordinates: l.coordinates,
          area_computed: l.area,
          width_meters: l.widthMeters,
          depth_meters: l.depthMeters,
          total_value: resolveLotTotalValueForGeneration(
            generateLotsZone.value,
            l.area,
            geoForm.value.total_value,
          ),
        })),
      },
    );
    toast.success(`${data.created} lotes gerados com polígonos!`);
    clearPreviewLayer();
    previewLots.value = [];
    generateLotsZone.value = null;
    genMode.value = 'simple';
    await loadZones();
    await loadLots();
    drawLotsOnMap();
  } catch (err) {
    const message = err?.response?.data?.message;
    const validationErrors = err?.response?.data?.errors;

    if (validationErrors && typeof validationErrors === 'object') {
      const firstError = Object.values(validationErrors).flat()?.[0];
      toast.error(firstError || message || 'Erro ao gerar lotes.');
    } else {
      toast.error(message || 'Erro ao gerar lotes.');
    }
  } finally {
    generating.value = false;
  }
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
      base_price_per_m2: item.base_price_per_m2 ?? 0,
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
    const payload = {
      ...form.value,
      base_price_per_m2: form.value.base_price_per_m2 > 0 ? form.value.base_price_per_m2 : null,
    };

    if (isEdit.value) {
      await api.post(`/developments/${route.params.id}/update`, payload);
      toast.success('Empreendimento atualizado.');
    } else {
      const { data } = await api.post('/developments', payload);
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
  document.addEventListener('keydown', handleMapInteractionEscape, true);

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
  document.removeEventListener('keydown', handleMapInteractionEscape, true);
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
