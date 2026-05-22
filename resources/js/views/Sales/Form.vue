<template>
  <div class="space-y-6 pb-10">

    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'sales.index' })">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-sid-dark">Nova Venda</h2>
        <p class="text-xs text-sid-secondary">Preencha as etapas abaixo para registrar a venda</p>
      </div>
    </div>

    <div
      v-if="draftSavedAt"
      class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-4 py-2"
    >
      <span class="flex items-center gap-1.5 text-xs text-slate-400">
        <DocumentIcon class="h-3.5 w-3.5" />
        Rascunho salvo às {{ formatDraftTime(draftSavedAt) }}
      </span>
      <button
        type="button"
        class="text-xs text-red-400 hover:text-red-600"
        @click="clearDraft"
      >
        Descartar rascunho
      </button>
    </div>

    <!-- ETAPA 1 — CLIENTE -->
    <div class="card p-5">
      <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">
        <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#c23028] text-white text-xs font-bold">1</span>
        Comprador
      </p>

      <div v-if="!clienteSelecionado && !mostrarFormCliente" class="space-y-3">
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="buscaCliente"
            type="search"
            name="sid-busca-cliente"
            v-bind="noAutofillInputAttrs"
            placeholder="Buscar cliente pelo nome ou CPF…"
            class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#c23028]/30 focus:border-[#c23028]"
            @input="buscarClientes"
          />
        </div>

        <div v-if="resultadosBusca.length" class="overflow-hidden rounded-lg border border-slate-200 divide-y divide-slate-100">
          <button
            v-for="c in resultadosBusca"
            :key="c.id"
            type="button"
            class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
            @click="selecionarCliente(c)"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#fdf3f2] text-sm font-semibold text-[#c23028]">
              {{ iniciais(c.name) }}
            </div>
            <div>
              <p class="text-sm font-medium text-slate-800">{{ c.name }}</p>
              <p class="text-xs text-slate-500">CPF {{ c.cpf }}{{ c.phone ? ' · ' + c.phone : '' }}</p>
            </div>
          </button>
        </div>

        <p v-if="buscaCliente.length >= 2 && !resultadosBusca.length && !buscando" class="text-sm text-slate-500">
          Nenhum cliente encontrado.
        </p>

        <button
          type="button"
          class="flex items-center gap-2 text-sm font-medium text-[#c23028] hover:text-[#d44840]"
          @click="mostrarFormCliente = true"
        >
          <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#fdf3f2] ring-1 ring-[#f5c4c0]">
            <PlusIcon class="h-3 w-3 stroke-2" />
          </span>
          Cadastrar novo cliente
        </button>
      </div>

      <div v-if="mostrarFormCliente && !clienteSelecionado" class="relative space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium text-slate-700">Novo cliente — dados básicos</p>
          <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click="cancelarNovoCliente">
            Voltar à busca
          </button>
        </div>

        <form
          autocomplete="off"
          class="grid grid-cols-1 gap-3 sm:grid-cols-2"
          @submit.prevent="salvarNovoCliente"
        >
          <!-- Isca: absorve autofill do Chrome antes dos campos visíveis -->
          <div
            class="pointer-events-none absolute -left-[9999px] h-0 w-0 overflow-hidden opacity-0"
            aria-hidden="true"
          >
            <input type="text" tabindex="-1" autocomplete="off" />
            <input type="password" tabindex="-1" autocomplete="new-password" />
          </div>

          <div class="sm:col-span-2">
            <p class="mb-1 text-xs font-medium text-slate-600">Nome do cliente *</p>
            <input
              :value="novoCliente.name"
              type="text"
              name="sid-fld-a7k2"
              v-bind="noAutofillInputAttrs"
              placeholder="Nome do cliente"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onNameInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">CPF *</p>
            <input
              :value="novoCliente.cpf"
              type="text"
              inputmode="numeric"
              name="sid-fld-b3m9"
              v-bind="noAutofillInputAttrs"
              placeholder="000.000.000-00"
              maxlength="14"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onCpfInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">RG</p>
            <input
              :value="novoCliente.rg"
              type="text"
              name="sid-fld-c8n1"
              v-bind="noAutofillInputAttrs"
              placeholder="0000000"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onRgInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Órgão emissor</p>
            <input
              :value="novoCliente.rg_issuer"
              type="text"
              name="sid-fld-d4p6"
              v-bind="noAutofillInputAttrs"
              placeholder="SSP/BA"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onRgIssuerInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Profissão</p>
            <input
              :value="novoCliente.profession"
              type="text"
              name="sid-fld-f1r3"
              v-bind="noAutofillInputAttrs"
              placeholder="Ex.: Comerciante"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onProfessionInput"
            />
          </div>

          <div>
            <SelectInput
              v-model="novoCliente.marital_status"
              label="Estado civil"
              :options="maritalStatusOptions"
              placeholder="Selecione…"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Telefone</p>
            <div class="relative">
              <input
                :value="novoCliente.phone"
                type="text"
                inputmode="numeric"
                name="sid-fld-e2q4"
                v-bind="noAutofillInputAttrs"
                placeholder="(74) 9 0000-0000"
                maxlength="16"
                :class="[maskedInputClass, whatsappStatus || otpVerified ? 'pr-32' : '']"
                readonly
                @mousedown="enableInputOnMousedown"
                @focus="enableInputOnMousedown"
                @input="onPhoneInput"
                @blur="checkWhatsapp"
              />

              <span
                v-if="otpVerified"
                :class="confirmationBadgeClass"
                class="absolute right-2 top-1.5 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
              >
                Verificado
              </span>

              <span
                v-else-if="whatsappStatus === 'has'"
                :class="confirmationBadgeClass"
                class="absolute right-2 top-1.5 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
              >
                WhatsApp
              </span>

              <span
                v-else-if="whatsappStatus === 'no'"
                :class="badgeColors.warning"
                class="absolute right-2 top-1.5 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
              >
                Sem WhatsApp
              </span>

              <span
                v-else-if="whatsappStatus === 'checking'"
                :class="badgeColors.neutral"
                class="absolute right-2 top-1.5 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
              >
                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                Verificando
              </span>

              <button
                v-else-if="whatsappStatus === 'error'"
                type="button"
                :class="badgeColors.danger"
                class="absolute right-2 top-1.5 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
                title="Clique para tentar novamente"
                @click="checkWhatsapp"
              >
                Tentar novamente
              </button>
            </div>

            <div v-if="whatsappStatus === 'has' && !otpVerified" class="mt-2">
              <div v-if="!otpSent" class="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  :disabled="otpSending"
                  class="flex items-center gap-1.5 rounded-lg bg-[#c23028] px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-[#d44840] disabled:opacity-50"
                  @click="sendOtp"
                >
                  <svg
                    v-if="otpSending"
                    class="h-3 w-3 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                  </svg>
                  {{ otpSending ? 'Enviando…' : 'Confirmar via WhatsApp' }}
                </button>
                <span class="text-xs text-slate-400">Enviaremos um código de verificação</span>
              </div>

              <div v-else class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <input
                    v-model="otpCode"
                    type="text"
                    inputmode="numeric"
                    maxlength="4"
                    placeholder="0000"
                    class="w-20 rounded-lg border border-[#f5c4c0] bg-[#fdf3f2] px-3 py-1.5 text-center text-base font-bold tracking-widest text-[#1c0a06] focus:border-[#c23028] focus:outline-none focus:ring-2 focus:ring-[#c23028]/30"
                    @input="onOtpInput"
                    @keyup.enter="verifyOtp"
                  />
                  <button
                    type="button"
                    :disabled="otpCode.length !== 4 || otpVerifying"
                    class="rounded-lg bg-[#c23028] px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-[#d44840] disabled:opacity-40"
                    @click="verifyOtp"
                  >
                    {{ otpVerifying ? 'Verificando…' : 'Confirmar' }}
                  </button>
                  <button
                    type="button"
                    class="text-xs text-slate-400 hover:text-slate-600"
                    @click="resetOtp"
                  >
                    Cancelar
                  </button>
                </div>

                <p v-if="otpError" class="text-xs text-red-500">{{ otpError }}</p>

                <p class="text-xs text-slate-400">
                  Código enviado para {{ novoCliente.phone }}.
                  <button
                    v-if="otpCountdown <= 0"
                    type="button"
                    class="text-[#c23028] hover:underline"
                    @click="sendOtp"
                  >
                    Reenviar código
                  </button>
                  <span v-else>Reenviar em {{ otpCountdown }}s</span>
                </p>
              </div>
            </div>

            <div
              v-if="(whatsappStatus === null || whatsappStatus === 'error' || whatsappStatus === 'no') && !otpVerified"
              class="mt-1 flex items-center gap-2"
            >
              <label class="flex cursor-pointer select-none items-center gap-1.5">
                <input
                  v-model="whatsappManual"
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 text-[#c23028] focus:ring-[#c23028]/30"
                />
                <span class="text-xs text-slate-500">Confirmar WhatsApp manualmente</span>
              </label>
            </div>
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">CEP</p>
            <div class="relative">
              <input
                :value="novoCliente.cep"
                type="text"
                inputmode="numeric"
                name="sid-fld-f9r3"
                v-bind="noAutofillInputAttrs"
                placeholder="00000-000"
                maxlength="9"
                :class="[maskedInputClass, buscandoCep ? 'pr-8' : '']"
                readonly
                @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
                @input="onCepInput"
              />
              <svg
                v-if="buscandoCep"
                class="absolute right-2 top-2.5 h-4 w-4 animate-spin text-[#c23028]"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
              </svg>
            </div>
            <p v-if="erroCep" class="mt-1 text-xs text-red-500">{{ erroCep }}</p>
          </div>

          <div class="sm:col-span-2">
            <p class="mb-1 text-xs font-medium text-slate-600">Logradouro</p>
            <input
              :value="novoCliente.address"
              type="text"
              name="sid-fld-g1s8"
              v-bind="noAutofillInputAttrs"
              placeholder="Rua, avenida…"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onAddressInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Número</p>
            <input
              :value="novoCliente.address_number"
              type="text"
              name="sid-fld-g2n1"
              v-bind="noAutofillInputAttrs"
              placeholder="123"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onAddressNumberInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Bairro</p>
            <input
              :value="novoCliente.neighborhood"
              type="text"
              name="sid-fld-g3b4"
              v-bind="noAutofillInputAttrs"
              placeholder="Centro"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onNeighborhoodInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Cidade</p>
            <input
              :value="novoCliente.city"
              type="text"
              name="sid-fld-h5t2"
              v-bind="noAutofillInputAttrs"
              placeholder="Cafarnaum"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onCityInput"
            />
          </div>

          <div>
            <p class="mb-1 text-xs font-medium text-slate-600">Estado</p>
            <input
              :value="novoCliente.state"
              type="text"
              name="sid-fld-i6u7"
              v-bind="noAutofillInputAttrs"
              placeholder="BA"
              maxlength="2"
              :class="maskedInputClass"
              readonly
              @mousedown="enableInputOnMousedown"
              @focus="enableInputOnMousedown"
              @input="onStateInput"
            />
          </div>
        </form>

        <div class="flex justify-end">
          <Button type="button" variant="primary" :disabled="salvandoCliente" @click="salvarNovoCliente">
            {{ salvandoCliente ? 'Salvando…' : 'Usar este cliente' }}
          </Button>
        </div>
      </div>

      <div v-if="clienteSelecionado" class="flex items-center gap-3 rounded-lg border border-[#fbe4e2] bg-[#fdf3f2] px-4 py-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#c23028] text-sm font-semibold text-white">
          {{ iniciais(clienteSelecionado.name) }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-[#1c0a06]">{{ clienteSelecionado.name }}</p>
          <p class="text-xs text-[#7a4535]">
            CPF {{ clienteSelecionado.cpf }}
            <span v-if="clienteSelecionado.phone"> · {{ clienteSelecionado.phone }}</span>
          </p>
        </div>
        <button type="button" class="shrink-0 text-xs font-medium text-[#c23028] hover:text-[#d44840]" @click="trocarCliente">
          Trocar
        </button>
      </div>

      <div v-if="clienteSelecionado" class="mt-3 space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-xs font-medium text-slate-500">
            Co-compradores <span class="text-slate-300">(opcional)</span>
          </p>
          <button
            v-if="!mostrarBuscaCoComprador"
            type="button"
            class="text-xs font-medium text-[#c23028] hover:text-[#d44840]"
            @click="mostrarBuscaCoComprador = true"
          >
            + Adicionar co-comprador
          </button>
        </div>

        <div v-if="mostrarBuscaCoComprador" class="relative">
          <input
            v-model="buscaCoComprador"
            type="search"
            name="sid-busca-co-comprador"
            v-bind="noAutofillInputAttrs"
            placeholder="Buscar co-comprador pelo nome ou CPF…"
            class="w-full rounded-lg border border-slate-200 py-2 pl-3 pr-3 text-sm focus:border-[#c23028] focus:outline-none focus:ring-2 focus:ring-[#c23028]/20"
            @input="buscarCoCompradores"
          />
          <div
            v-if="resultadosCoComprador.length"
            class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg divide-y divide-slate-100"
          >
            <button
              v-for="c in resultadosCoComprador"
              :key="c.id"
              type="button"
              class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-slate-50"
              @click="adicionarCoComprador(c)"
            >
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                {{ iniciais(c.name) }}
              </div>
              <div>
                <p class="text-sm font-medium text-slate-800">{{ c.name }}</p>
                <p class="text-xs text-slate-500">CPF {{ c.cpf }}</p>
              </div>
            </button>
          </div>
        </div>

        <div
          v-for="co in coBuyers"
          :key="co.id"
          class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
        >
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
            {{ iniciais(co.name) }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-700">{{ co.name }}</p>
            <p class="text-xs text-slate-400">Co-comprador · CPF {{ co.cpf }}</p>
          </div>
          <button
            type="button"
            class="shrink-0 text-xs text-red-500 hover:text-red-700"
            @click="removerCoComprador(co.id)"
          >
            Remover
          </button>
        </div>
      </div>
    </div>

    <!-- ETAPA 2 — LOTE -->
    <div class="card p-5" :class="{ 'pointer-events-none opacity-40': !clienteSelecionado }">
      <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">
        <span
          class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold"
          :class="clienteSelecionado ? 'bg-[#c23028] text-white' : 'bg-slate-200 text-slate-400'"
        >2</span>
        Lote
      </p>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <SelectInput
          v-model="form.development_id"
          label="Empreendimento"
          :options="developmentOptions"
          placeholder="Selecione…"
          :searchable="true"
          @update:model-value="onDevelopmentChange"
        />
        <SelectInput
          v-model="form.lot_id"
          label="Lote disponível"
          :options="lotOptions"
          placeholder="Selecione o lote…"
          :searchable="true"
          :disabled="!form.development_id"
        />
        <div v-if="loteSelecionado">
          <p class="mb-1 block text-sm font-medium text-slate-700">Lote selecionado</p>
          <div
            class="flex min-h-[42px] items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800"
          >
            <span class="font-semibold">Q{{ loteSelecionado.block ?? '–' }} · L{{ loteSelecionado.number }}</span>
            <span v-if="loteSelecionado.area || loteSelecionado.total_value" class="ml-1 text-slate-500">
              <template v-if="loteSelecionado.area">{{ loteSelecionado.area }}m²</template>
              <template v-if="loteSelecionado.total_value">
                <template v-if="loteSelecionado.area"> · </template>
                {{ formatCurrency(loteSelecionado.total_value) }}
              </template>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- ETAPA 3 — VALORES -->
    <div class="card p-5" :class="{ 'pointer-events-none opacity-40': !form.lot_id }">
      <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">
        <span
          class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold"
          :class="form.lot_id ? 'bg-[#c23028] text-white' : 'bg-slate-200 text-slate-400'"
        >3</span>
        Valores e Parcelas
      </p>
      <div class="space-y-5">
        <CurrencyInput v-model="form.total_value" label="Valor total (tabela) *" />

        <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 space-y-3">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Venda à vista</p>
              <p class="mt-0.5 text-xs text-slate-500">
                Preencha o valor final, o desconto em reais ou em percentual. Qualquer um dos campos ativa a venda à vista.
              </p>
            </div>
            <button
              v-if="isCashSale"
              type="button"
              class="shrink-0 text-xs font-medium text-[#c23028] hover:text-[#d44840]"
              @click="clearCashSaleFields"
            >
              Limpar venda à vista
            </button>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <CurrencyInput
              :model-value="form.cash_value"
              label="Valor à vista (final)"
              placeholder="Valor pago à vista"
              @update:model-value="onCashValueInput"
            />
            <CurrencyInput
              :model-value="form.discount_amount"
              label="Desconto (R$)"
              placeholder="R$ 0,00"
              @update:model-value="onDiscountAmountInput"
            />
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-sid-dark">Desconto (%)</label>
              <input
                :value="form.discount_percent"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                placeholder="Ex.: 10"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#c23028] focus:outline-none focus:ring-2 focus:ring-[#c23028]/20"
                @input="onDiscountPercentInput"
              />
            </div>
          </div>
          <div
            v-if="isCashSale"
            class="grid grid-cols-1 gap-2 rounded-lg border border-[#e8dcc8] bg-[#faf5ee] px-3 py-2 text-xs text-[#7a4535] sm:grid-cols-3"
          >
            <p>
              <span class="text-[#a07a28]">Desconto:</span>
              <span class="font-semibold text-[#1c0a06]">{{ formatCurrency(appliedDiscountAmount) }}</span>
              <span v-if="appliedDiscountPercent > 0"> ({{ appliedDiscountPercentLabel }})</span>
            </p>
            <p>
              <span class="text-[#a07a28]">Valor a pagar:</span>
              <span class="font-semibold text-[#1c0a06]">{{ formatCurrency(form.cash_value) }}</span>
            </p>
            <p>
              <span class="text-[#a07a28]">Tabela:</span>
              <span class="font-semibold text-[#1c0a06]">{{ formatCurrency(form.total_value) }}</span>
            </p>
          </div>
        </div>

        <p v-if="isCashSale" class="rounded-lg border border-[#e8dcc8] bg-[#faf5ee] px-3 py-2 text-xs text-[#7a4535]">
          Venda à vista — parcelamento oculto. O valor à vista é o valor final pago pelo comprador.
        </p>

        <div v-if="!isCashSale" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <CurrencyInput
              v-model="form.down_payment"
              label="Entrada *"
              @update:model-value="onDownPaymentInput"
            />
            <p v-if="effectiveDownPaymentPercent" class="mt-1 text-xs text-slate-400">
              Sugestão: {{ effectiveDownPaymentPercent }}% do total
              <span v-if="paymentTermsSourceLabel"> ({{ paymentTermsSourceLabel }})</span>
            </p>
          </div>
          <Input v-model="form.installments_count" label="Nº de parcelas *" type="number" min="1" />
        </div>

        <div v-if="!isCashSale" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs text-slate-500">Saldo a financiar</p>
            <p class="text-xl font-bold text-slate-800">{{ formatCurrency(form.financed_value || 0) }}</p>
          </div>
          <div class="rounded-lg border border-[#e8dcc8] bg-[#faf5ee] px-4 py-3">
            <p class="text-xs text-[#a07a28]">Valor de cada parcela</p>
            <p class="text-xl font-bold text-[#7a4535]">{{ formatCurrency(form.installment_value || 0) }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Flatpickr
            v-if="!isCashSale"
            v-model="form.first_due_date"
            label="Vencimento da 1ª parcela *"
          />
          <Input
            v-if="!isCashSale"
            v-model="form.payment_day"
            label="Dia do vencimento (mês) *"
            type="number"
            min="1"
            max="31"
          />
          <Flatpickr v-model="form.sale_date" label="Data da venda *" />
          <div v-if="isCashSale" class="hidden sm:block" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Observações</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#c23028] focus:outline-none focus:ring-2 focus:ring-[#c23028]/20"
            placeholder="Anotações internas…"
          />
        </div>
      </div>
    </div>

    <div v-if="pronto" class="card border-[#fbe4e2] bg-[#fdf3f2] p-5">
      <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-[#c23028]">Resumo da venda</p>
      <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
        <div>
          <p class="text-xs text-[#7a4535]">Comprador</p>
          <p class="truncate font-medium text-[#1c0a06]">{{ clienteSelecionado?.name }}</p>
        </div>
        <div>
          <p class="text-xs text-[#7a4535]">Lote</p>
          <p class="font-medium text-[#1c0a06]">Q{{ loteSelecionado?.block }} · L{{ loteSelecionado?.number }}</p>
        </div>
        <div>
          <p class="text-xs text-[#7a4535]">Total</p>
          <p class="font-medium text-[#1c0a06]">{{ formatCurrency(form.total_value) }}</p>
        </div>
        <div>
          <p class="text-xs text-[#7a4535]">Pagamento</p>
          <p class="font-medium text-[#1c0a06]">
            <template v-if="isCashSale">
              À vista · {{ formatCurrency(form.cash_value) }}
              <span v-if="appliedDiscountAmount > 0" class="text-[#7a4535]">
                (desc. {{ formatCurrency(appliedDiscountAmount) }})
              </span>
            </template>
            <template v-else>{{ form.installments_count }}x {{ formatCurrency(form.installment_value) }}</template>
          </p>
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-3">
      <Button type="button" variant="outline" @click="$router.push({ name: 'sales.index' })">Cancelar</Button>
      <Button type="button" variant="primary" :disabled="!pronto || salvando" @click="registrarVenda">
        {{ salvando ? 'Registrando…' : (isCashSale ? 'Registrar Venda' : 'Registrar Venda e Gerar Parcelas') }}
      </Button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { useAlert } from '@/composables/useAlert';
import { getApiErrorMessage } from '@/utils/apiError';
import { enableInputOnMousedown, noAutofillInputAttrs } from '@/utils/noAutofill';
import Input from '@/components/Common/Input.vue';
import Flatpickr from '@/components/Common/Flatpickr.vue';
import CurrencyInput from '@/components/Common/CurrencyInput.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import { maritalStatusOptions } from '@/constants/maritalStatus';
import { formatCurrency } from '@/utils/format';
import { badgeColors, confirmationBadgeClass } from '@/utils/status';
import Button from '@/components/Common/Button.vue';
import {
  ArrowLeftIcon,
  DocumentIcon,
  MagnifyingGlassIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline';

const DRAFT_KEY = 'sid360_sale_draft';

const router = useRouter();
const toast = useToast();
const { confirm: confirmAlert } = useAlert();

const buscaCliente = ref('');
const resultadosBusca = ref([]);
const buscando = ref(false);
const clienteSelecionado = ref(null);
const mostrarFormCliente = ref(false);
const salvandoCliente = ref(false);

const coBuyers = ref([]);
const mostrarBuscaCoComprador = ref(false);
const buscaCoComprador = ref('');
const resultadosCoComprador = ref([]);
let debounceCoComprador = null;

const maskedInputClass =
  'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#c23028]/20 focus:border-[#c23028]';

const novoCliente = ref({
  name: '',
  cpf: '',
  rg: '',
  rg_issuer: '',
  profession: '',
  marital_status: '',
  phone: '',
  email: '',
  address: '',
  address_number: '',
  neighborhood: '',
  cep: '',
  city: 'Cafarnaum',
  state: 'BA',
});

const buscandoCep = ref(false);
const erroCep = ref('');

const whatsappStatus = ref(null);
const whatsappManual = ref(false);

const otpSent = ref(false);
const otpCode = ref('');
const otpVerified = ref(false);
const otpError = ref('');
const otpSending = ref(false);
const otpVerifying = ref(false);
const otpCountdown = ref(0);
let otpTimer = null;

const draftSavedAt = ref(null);
const isRestoringDraft = ref(false);

function maskCpf(val) {
  return val
    .replace(/\D/g, '')
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

function maskPhone(val) {
  const digits = val.replace(/\D/g, '').slice(0, 11);
  if (digits.length <= 10) {
    return digits.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
  }
  return digits.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
}

function maskCep(val) {
  return val.replace(/\D/g, '').slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2');
}

function onNameInput(e) {
  novoCliente.value.name = e.target.value;
}

function onRgInput(e) {
  novoCliente.value.rg = e.target.value;
}

function onRgIssuerInput(e) {
  novoCliente.value.rg_issuer = e.target.value;
}

function onProfessionInput(e) {
  novoCliente.value.profession = e.target.value;
}

function onAddressInput(e) {
  novoCliente.value.address = e.target.value;
}

function onAddressNumberInput(e) {
  novoCliente.value.address_number = e.target.value;
}

function onNeighborhoodInput(e) {
  novoCliente.value.neighborhood = e.target.value;
}

function onCityInput(e) {
  novoCliente.value.city = e.target.value;
}

function onStateInput(e) {
  novoCliente.value.state = e.target.value.toUpperCase().slice(0, 2);
  e.target.value = novoCliente.value.state;
}

function onCpfInput(e) {
  novoCliente.value.cpf = maskCpf(e.target.value);
  e.target.value = novoCliente.value.cpf;
}

function onPhoneInput(e) {
  novoCliente.value.phone = maskPhone(e.target.value);
  e.target.value = novoCliente.value.phone;
  whatsappStatus.value = null;
  resetOtp();
}

function onOtpInput(e) {
  otpCode.value = e.target.value.replace(/\D/g, '').slice(0, 4);
  e.target.value = otpCode.value;
}

async function sendOtp() {
  const raw = novoCliente.value.phone.replace(/\D/g, '');
  if (raw.length < 10) return;

  otpSending.value = true;
  otpError.value = '';

  try {
    await api.post('/whatsapp/send-otp', { phone: raw });
    otpSent.value = true;
    otpCode.value = '';
    otpVerified.value = false;
    startOtpCountdown();
  } catch {
    otpError.value = 'Falha ao enviar código. Tente novamente.';
  } finally {
    otpSending.value = false;
  }
}

async function verifyOtp() {
  if (otpCode.value.length !== 4) return;

  otpVerifying.value = true;
  otpError.value = '';

  try {
    const { data } = await api.post('/whatsapp/verify-otp', {
      phone: novoCliente.value.phone.replace(/\D/g, ''),
      code: otpCode.value,
    });
    if (data.valid) {
      otpVerified.value = true;
      whatsappStatus.value = 'has';
      clearInterval(otpTimer);
      otpCountdown.value = 0;
    }
  } catch (err) {
    otpError.value = err?.response?.data?.error ?? 'Código incorreto.';
  } finally {
    otpVerifying.value = false;
  }
}

function startOtpCountdown() {
  otpCountdown.value = 60;
  clearInterval(otpTimer);
  otpTimer = setInterval(() => {
    otpCountdown.value -= 1;
    if (otpCountdown.value <= 0) clearInterval(otpTimer);
  }, 1000);
}

function resetOtp() {
  otpSent.value = false;
  otpCode.value = '';
  otpVerified.value = false;
  otpError.value = '';
  otpCountdown.value = 0;
  clearInterval(otpTimer);
}

async function checkWhatsapp() {
  const raw = novoCliente.value.phone.replace(/\D/g, '');
  if (raw.length < 10) {
    whatsappStatus.value = null;
    return;
  }

  whatsappStatus.value = 'checking';

  try {
    const { data } = await api.get(`/whatsapp/check/${raw}`);
    whatsappStatus.value = data.has_whatsapp ? 'has' : 'no';
  } catch {
    whatsappStatus.value = 'error';
  }
}

function onCepInput(e) {
  novoCliente.value.cep = maskCep(e.target.value);
  e.target.value = novoCliente.value.cep;
  const digits = novoCliente.value.cep.replace(/\D/g, '');
  if (digits.length === 8) buscarCep(digits);
}

async function buscarCep(cep) {
  buscandoCep.value = true;
  erroCep.value = '';
  try {
    const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    const data = await res.json();

    if (!data.erro) {
      preencherEndereco(data.logradouro, data.localidade, data.uf, data.bairro);
      return;
    }

    const res2 = await fetch(`https://brasilapi.com.br/api/cep/v1/${cep}`);
    if (res2.ok) {
      const data2 = await res2.json();
      preencherEndereco(data2.street, data2.city, data2.state, data2.neighborhood);
      return;
    }

    erroCep.value = 'CEP não encontrado.';
  } catch {
    erroCep.value = 'Erro ao buscar CEP.';
  } finally {
    buscandoCep.value = false;
  }
}

function preencherEndereco(logradouro, cidade, estado, bairro) {
  if (logradouro) novoCliente.value.address = logradouro;
  if (bairro) novoCliente.value.neighborhood = bairro;
  if (cidade) novoCliente.value.city = cidade;
  if (estado) novoCliente.value.state = estado;
}

const form = ref({
  client_id: '',
  development_id: '',
  lot_id: '',
  sale_date: new Date().toISOString().slice(0, 10),
  total_value: 0,
  cash_value: 0,
  discount_amount: 0,
  discount_percent: '',
  down_payment: 0,
  financed_value: 0,
  installments_count: '',
  installment_value: 0,
  first_due_date: '',
  payment_day: '10',
  notes: '',
});

let debounce = null;

async function buscarClientes() {
  if (buscaCliente.value.length < 2) {
    resultadosBusca.value = [];
    return;
  }
  clearTimeout(debounce);
  debounce = setTimeout(async () => {
    buscando.value = true;
    try {
      const { data } = await api.get('/clients', { params: { search: buscaCliente.value, all: 1 } });
      resultadosBusca.value = (data.data ?? data).slice(0, 5);
    } finally {
      buscando.value = false;
    }
  }, 300);
}

function iniciais(nome) {
  if (!nome) return '?';
  return nome
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((n) => n[0].toUpperCase())
    .join('');
}

function selecionarCliente(c) {
  clienteSelecionado.value = c;
  form.value.client_id = c.id;
  resultadosBusca.value = [];
  buscaCliente.value = '';
  whatsappStatus.value = null;
}

function trocarCliente() {
  clienteSelecionado.value = null;
  form.value.client_id = '';
  mostrarFormCliente.value = false;
  coBuyers.value = [];
  mostrarBuscaCoComprador.value = false;
  buscaCoComprador.value = '';
  resultadosCoComprador.value = [];
}

async function buscarCoCompradores() {
  if (buscaCoComprador.value.length < 2) {
    resultadosCoComprador.value = [];
    return;
  }
  clearTimeout(debounceCoComprador);
  debounceCoComprador = setTimeout(async () => {
    try {
      const { data } = await api.get('/clients', {
        params: { search: buscaCoComprador.value, all: 1 },
      });
      const excludeIds = [clienteSelecionado.value?.id, ...coBuyers.value.map((c) => c.id)].filter(Boolean);
      const list = data.data ?? data;
      resultadosCoComprador.value = list.filter((c) => !excludeIds.includes(c.id)).slice(0, 5);
    } catch {
      resultadosCoComprador.value = [];
    }
  }, 300);
}

function adicionarCoComprador(c) {
  if (!coBuyers.value.find((x) => x.id === c.id)) {
    coBuyers.value.push(c);
  }
  buscaCoComprador.value = '';
  resultadosCoComprador.value = [];
  mostrarBuscaCoComprador.value = false;
}

function removerCoComprador(id) {
  coBuyers.value = coBuyers.value.filter((c) => c.id !== id);
}

function saveDraft() {
  try {
    const draft = {
      form: form.value,
      novoCliente: novoCliente.value,
      clienteSelecionado: clienteSelecionado.value,
      coBuyers: coBuyers.value,
      mostrarFormCliente: mostrarFormCliente.value,
      savedAt: new Date().toISOString(),
    };
    localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    draftSavedAt.value = new Date();
  } catch {
    // localStorage indisponível
  }
}

function clearDraft() {
  localStorage.removeItem(DRAFT_KEY);
  draftSavedAt.value = null;
}

function formatDraftTime(date) {
  if (!date) return '';
  return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

async function checkAndRestoreDraft() {
  try {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return;

    const draft = JSON.parse(raw);
    const age = Date.now() - new Date(draft.savedAt).getTime();
    if (age > 86400000) {
      clearDraft();
      return;
    }

    const savedTime = new Date(draft.savedAt).toLocaleString('pt-BR');
    const restore = await confirmAlert(
      'Rascunho encontrado',
      `Você tem um rascunho salvo em ${savedTime}.\n\nDeseja continuar de onde parou?`,
      'Sim, restaurar',
      'blue'
    );

    if (restore) {
      isRestoringDraft.value = true;
      if (draft.form) form.value = draft.form;
      if (draft.novoCliente) novoCliente.value = draft.novoCliente;
      if (draft.clienteSelecionado) clienteSelecionado.value = draft.clienteSelecionado;
      if (draft.coBuyers) coBuyers.value = draft.coBuyers;
      if (draft.mostrarFormCliente !== undefined) {
        mostrarFormCliente.value = draft.mostrarFormCliente;
      }
      draftSavedAt.value = new Date(draft.savedAt);
      if (form.value.development_id) {
        await onDevelopmentChange();
      }
      isRestoringDraft.value = false;
    } else {
      clearDraft();
    }
  } catch {
    clearDraft();
  }
}

async function saveWhatsappStatus(clientId, status) {
  try {
    await api.patch(`/clients/${clientId}/whatsapp-status`, { status });
  } catch {
    // não bloqueia o cadastro
  }
}

function cancelarNovoCliente() {
  mostrarFormCliente.value = false;
  erroCep.value = '';
  whatsappStatus.value = null;
  whatsappManual.value = false;
  resetOtp();
  novoCliente.value = {
    name: '',
    cpf: '',
    rg: '',
    rg_issuer: '',
    profession: '',
    marital_status: '',
    phone: '',
    email: '',
    address: '',
    address_number: '',
    neighborhood: '',
    cep: '',
    city: 'Cafarnaum',
    state: 'BA',
  };
}

async function salvarNovoCliente() {
  if (!novoCliente.value.name || !novoCliente.value.cpf) {
    toast.error('Nome e CPF são obrigatórios.');
    return;
  }
  salvandoCliente.value = true;
  try {
    const { cep: _cep, ...payload } = novoCliente.value;
    const { data } = await api.post('/clients', payload);
    const cliente = data.data ?? data;
    selecionarCliente(cliente);
    mostrarFormCliente.value = false;

    if (otpVerified.value) {
      await saveWhatsappStatus(cliente.id, 'confirmed');
    } else if (whatsappStatus.value === 'no') {
      await saveWhatsappStatus(cliente.id, 'none');
    } else if (whatsappManual.value) {
      await saveWhatsappStatus(cliente.id, 'confirmed');
    }

    resetOtp();
    toast.success('Cliente cadastrado com sucesso.');
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao cadastrar cliente.'));
  } finally {
    salvandoCliente.value = false;
  }
}

const developments = ref([]);
const lots = ref([]);

const developmentOptions = computed(() =>
  developments.value.map((d) => ({
    value: String(d.id),
    label: `${d.name} (${d.available_lots_count ?? 0})`,
  }))
);

const lotOptions = computed(() =>
  lots.value
    .filter((l) => l.status === 'available')
    .map((l) => ({
      value: String(l.id),
      label: `Q${l.block ?? '?'} · L${l.number}${l.area ? ' · ' + l.area + 'm²' : ''}`,
    }))
);

const loteSelecionado = computed(
  () => lots.value.find((l) => String(l.id) === String(form.value.lot_id)) ?? null
);

const entradaEditadaManualmente = ref(false);
const discountEditSource = ref(null);

const isCashSale = computed(() => {
  const total = Number(form.value.total_value) || 0;
  if (total <= 0) {
    return false;
  }
  const cash = Number(form.value.cash_value) || 0;
  const discount = Number(form.value.discount_amount) || 0;
  const percent = parseDiscountPercent(form.value.discount_percent);
  return cash > 0 || discount > 0 || percent > 0;
});

const appliedDiscountAmount = computed(() => {
  const total = Number(form.value.total_value) || 0;
  const cash = Number(form.value.cash_value) || 0;
  if (total <= 0) {
    return 0;
  }
  return Math.max(0, total - cash);
});

const appliedDiscountPercent = computed(() => {
  const total = Number(form.value.total_value) || 0;
  if (total <= 0) {
    return 0;
  }
  return Math.round((appliedDiscountAmount.value / total) * 10000) / 100;
});

const appliedDiscountPercentLabel = computed(() => {
  const value = appliedDiscountPercent.value;
  if (value <= 0) {
    return '';
  }
  return `${String(value).replace('.', ',')}%`;
});

function parseDiscountPercent(value) {
  const normalized = String(value ?? '').trim().replace(',', '.');
  if (!normalized) {
    return 0;
  }
  const parsed = parseFloat(normalized);
  return Number.isFinite(parsed) ? Math.min(Math.max(parsed, 0), 100) : 0;
}

function formatDiscountPercentInput(percent) {
  const rounded = Math.round(percent * 100) / 100;
  if (rounded <= 0) {
    return '';
  }
  return rounded % 1 === 0 ? String(Math.round(rounded)) : String(rounded).replace('.', ',');
}

function syncCashDiscountFromCashValue() {
  const total = Number(form.value.total_value) || 0;
  let cash = Number(form.value.cash_value) || 0;
  cash = Math.min(Math.max(0, cash), total);
  form.value.cash_value = cash;
  form.value.discount_amount = total - cash;
  const percent = total > 0 ? (form.value.discount_amount / total) * 100 : 0;
  form.value.discount_percent = formatDiscountPercentInput(percent);
}

function syncCashDiscountFromDiscountAmount() {
  const total = Number(form.value.total_value) || 0;
  let discount = Number(form.value.discount_amount) || 0;
  discount = Math.min(Math.max(0, discount), total);
  form.value.discount_amount = discount;
  form.value.cash_value = total - discount;
  const percent = total > 0 ? (discount / total) * 100 : 0;
  form.value.discount_percent = formatDiscountPercentInput(percent);
}

function syncCashDiscountFromDiscountPercent() {
  const total = Number(form.value.total_value) || 0;
  const percent = parseDiscountPercent(form.value.discount_percent);
  const discount = Math.round(total * percent / 100);
  form.value.discount_amount = discount;
  form.value.cash_value = total - discount;
  form.value.discount_percent = formatDiscountPercentInput(percent);
}

function onCashValueInput(value) {
  discountEditSource.value = 'cash';
  form.value.cash_value = Number(value) || 0;
  syncCashDiscountFromCashValue();
}

function onDiscountAmountInput(value) {
  discountEditSource.value = 'amount';
  form.value.discount_amount = Number(value) || 0;
  syncCashDiscountFromDiscountAmount();
}

function onDiscountPercentInput(event) {
  discountEditSource.value = 'percent';
  form.value.discount_percent = event.target.value;
  syncCashDiscountFromDiscountPercent();
}

function clearCashSaleFields() {
  discountEditSource.value = null;
  form.value.cash_value = 0;
  form.value.discount_amount = 0;
  form.value.discount_percent = '';
  if (!form.value.installments_count) {
    form.value.installments_count = '1';
  }
  if (!entradaEditadaManualmente.value) {
    suggestDownPayment();
  } else {
    recalcular();
  }
}

const effectiveDownPaymentPercent = computed(() => {
  const lot = loteSelecionado.value;
  if (!lot) return 20;
  return Number(lot.effective_down_payment_percent ?? 20);
});

const paymentTermsSourceLabel = computed(() => {
  const lot = loteSelecionado.value;
  if (!lot) return '';
  return lot.uses_development_payment_terms ? 'empreendimento' : 'lote';
});

function recalcular() {
  const total = Number(form.value.total_value) || 0;
  const down = Number(form.value.down_payment) || 0;
  const n = parseInt(form.value.installments_count, 10) || 1;
  const financed = Math.max(0, total - down);
  form.value.financed_value = financed;
  form.value.installment_value = n > 0 ? Math.round(financed / n) : 0;
}

function onDownPaymentInput() {
  entradaEditadaManualmente.value = true;
}

function suggestDownPayment() {
  if (isCashSale.value) return;
  const total = Number(form.value.total_value) || 0;
  if (total <= 0) return;
  const pct = effectiveDownPaymentPercent.value / 100;
  form.value.down_payment = Math.round(total * pct);
  recalcular();
}

async function onDevelopmentChange() {
  form.value.lot_id = '';
  lots.value = [];
  if (!form.value.development_id) return;
  try {
    const { data } = await api.get(`/developments/${form.value.development_id}/lots`, {
      params: { all: 1 },
    });
    lots.value = data.data ?? data;
  } catch {
    lots.value = [];
  }
}

watch(
  () => form.value.lot_id,
  (id) => {
    const lote = lots.value.find((l) => String(l.id) === String(id));
    entradaEditadaManualmente.value = false;
    if (lote?.total_value) {
      form.value.total_value = Number(lote.total_value);
    }
    suggestDownPayment();
  }
);

watch(
  () => form.value.total_value,
  () => {
    if (isCashSale.value && discountEditSource.value === 'percent') {
      syncCashDiscountFromDiscountPercent();
    } else if (isCashSale.value && discountEditSource.value === 'amount') {
      syncCashDiscountFromDiscountAmount();
    } else if (isCashSale.value && discountEditSource.value === 'cash') {
      syncCashDiscountFromCashValue();
    } else if (isCashSale.value) {
      syncCashDiscountFromCashValue();
    }

    if (!entradaEditadaManualmente.value) {
      suggestDownPayment();
    } else {
      recalcular();
    }
  }
);

watch(
  () => [form.value.down_payment, form.value.installments_count, isCashSale.value],
  () => {
    if (isCashSale.value) {
      form.value.financed_value = 0;
      form.value.installment_value = 0;
      form.value.down_payment = 0;
      form.value.installments_count = '';
      return;
    }
    recalcular();
  }
);

let draftDebounce = null;

watch(
  [form, novoCliente, clienteSelecionado, mostrarFormCliente],
  () => {
    if (isRestoringDraft.value) return;
    clearTimeout(draftDebounce);
    draftDebounce = setTimeout(saveDraft, 2000);
  },
  { deep: true }
);

const salvando = ref(false);

const pronto = computed(() => {
  if (!clienteSelecionado.value || !form.value.lot_id || form.value.total_value <= 0 || !form.value.sale_date) {
    return false;
  }
  if (isCashSale.value) {
    const cash = Number(form.value.cash_value) || 0;
    const total = Number(form.value.total_value) || 0;
    return cash > 0 && cash <= total;
  }
  return (
    Number(form.value.installments_count) > 0 &&
    Boolean(form.value.first_due_date)
  );
});

async function registrarVenda() {
  salvando.value = true;
  try {
    const cash = isCashSale.value;
    const payload = {
      client_id: Number(form.value.client_id),
      lot_id: Number(form.value.lot_id),
      sale_date: form.value.sale_date,
      total_value: Number(form.value.total_value),
      cash_value: cash ? Number(form.value.cash_value) : null,
      discount_amount: cash ? Number(form.value.discount_amount) || 0 : 0,
      discount_percent: cash && appliedDiscountPercent.value > 0 ? appliedDiscountPercent.value : null,
      down_payment: cash ? 0 : Number(form.value.down_payment),
      financed_value: cash ? 0 : Number(form.value.financed_value),
      installments_count: cash ? 0 : Number(form.value.installments_count),
      installment_value: cash ? 0 : Number(form.value.installment_value),
      first_due_date: cash ? form.value.sale_date : form.value.first_due_date,
      payment_day: cash ? 1 : Number(form.value.payment_day),
      notes: form.value.notes || null,
      co_buyer_ids: coBuyers.value.map((c) => c.id),
    };
    const { data } = await api.post('/sales', payload);
    const venda = data.data ?? data;
    toast.success(
      cash
        ? 'Venda à vista registrada com sucesso.'
        : `Venda registrada! ${venda.installments_count ?? form.value.installments_count} parcelas geradas.`
    );
    clearDraft();
    router.push({ name: 'sales.show', params: { id: venda.id }, query: { registered: '1' } });
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao registrar venda.'));
  } finally {
    salvando.value = false;
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/developments', {
      params: { all: 1, has_available_lots: 1 },
    });
    developments.value = data.data ?? data;
  } catch {
    developments.value = [];
  }
  await checkAndRestoreDraft();
});

onUnmounted(() => {
  clearInterval(otpTimer);
});
</script>
