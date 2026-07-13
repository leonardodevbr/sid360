<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="$router.push({ name: 'sales.index' })">
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div class="min-w-0 flex-1">
        <h2 class="text-lg font-semibold text-slate-800">Venda #{{ sale?.id }}</h2>
        <p class="text-xs text-slate-500">{{ sale?.client?.name }}</p>
        <p v-if="sale?.client?.cpf" class="mt-0.5 text-xs text-slate-500">
          CPF {{ formatCpf(sale.client.cpf) }}
        </p>
        <p
          v-if="sale?.client?.phone"
          class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500"
        >
          <ChatBubbleLeftRightIcon class="h-3.5 w-3.5 shrink-0 text-slate-400" />
          <a
            v-if="clientWhatsAppUrl"
            :href="clientWhatsAppUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="font-medium text-action hover:underline"
          >
            {{ formatPhone(sale.client.phone) }}
          </a>
          <span v-else>{{ formatPhone(sale.client.phone) }}</span>
          <span
            v-if="sale.client.whatsapp_status === 'confirmed'"
            class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"
          >
            confirmado
          </span>
        </p>
        <p v-else-if="sale?.client" class="mt-0.5 text-xs text-amber-700">
          WhatsApp não cadastrado
        </p>
        <p v-if="temMultiplosLotes" class="mt-0.5 text-xs text-slate-500">
          Lotes {{ sale.lots.map((l) => l.number).join(', ') }}
          <span v-if="sale.lot?.development?.name"> · {{ sale.lot.development.name }}</span>
        </p>
        <p v-else-if="sale?.lot?.full_address" class="mt-0.5 text-xs text-slate-500">
          {{ sale.lot.full_address }}
        </p>
      </div>
      <div v-if="sale && sale.status !== 'cancelled'" class="flex shrink-0 gap-2">
        <Button
          type="button"
          variant="outline"
          @click="openChangeLotModal"
        >
          Trocar lote
        </Button>
        <Button
          type="button"
          variant="danger"
          :loading="cancellingSale"
          @click="handleCancelSale"
        >
          Cancelar venda
        </Button>
      </div>
    </div>

    <div v-if="loading" class="card p-12 text-center text-slate-400">Carregando...</div>

    <template v-else-if="sale">
      <div
        v-if="showRegistrationSuccess"
        class="card border-[#e8dcc8] bg-[#faf5ee] p-5"
      >
        <p class="text-sm font-semibold text-[#1c0a06]">Venda registrada com sucesso</p>
        <p class="mt-1 text-xs text-[#7a4535]">
          Imprima o contrato para assinatura e, após assinado, envie o arquivo digitalizado abaixo.
        </p>
      </div>

      <div v-if="sale.status === 'cancelled'" class="card border-red-200 bg-red-50 p-5">
        <p class="text-sm font-semibold text-red-800">Venda cancelada</p>
        <p v-if="sale.cancelled_at" class="mt-1 text-xs text-red-700">
          Cancelada em {{ formatDateTime(sale.cancelled_at) }}
        </p>
        <p v-if="sale.cancellation_reason" class="mt-2 text-xs text-red-700">
          <span class="font-medium">Motivo:</span> {{ sale.cancellation_reason }}
        </p>
        <p class="mt-2 text-xs text-red-600">
          {{ temMultiplosLotes ? 'Os lotes foram liberados' : 'O lote foi liberado' }} e as parcelas pendentes foram
          canceladas. Parcelas já pagas e documentos foram preservados.
        </p>
      </div>

      <div v-if="temMultiplosLotes" class="card p-5">
        <h3 class="mb-3 text-sm font-semibold text-slate-800">Lotes desta venda</h3>
        <p class="mb-3 text-xs text-slate-500">
          Venda com múltiplos lotes — 1 contrato e 1 carnê cobrem todos os lotes abaixo.
        </p>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="lote in sale.lots"
            :key="lote.id"
            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800"
          >
            <span class="font-semibold">Q{{ lote.block ?? '–' }} · L{{ lote.number }}</span>
            <span v-if="resolveLotArea(lote)" class="ml-1 text-slate-500">{{ resolveLotArea(lote) }}m²</span>
          </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">
          Área total: {{ lotesAreaTotal }}m²
        </p>
      </div>

      <div class="card p-5">
        <h3 class="mb-1 text-sm font-semibold text-slate-800">Contrato</h3>
        <p class="mb-4 text-xs text-slate-500">
          Pré-visualize a minuta com marca d'água antes de fechar, ou baixe o contrato oficial para assinatura.
        </p>

        <div class="mb-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
          <div>
            <p class="text-sm font-medium text-slate-800">Texto das medidas no contrato</p>
            <p class="mt-0.5 text-xs text-slate-500">
              Ajuste o trecho que descreve área e medidas de cada lote. Se deixar vazio, usa o texto padrão do lote (ou o gerado automaticamente).
            </p>
          </div>

          <div
            v-for="lot in contractLots"
            :key="lot.id"
            class="space-y-1"
          >
            <label class="block text-xs font-medium text-slate-600">
              {{ lotContractLabel(lot) }}
            </label>
            <textarea
              v-model="contractMeasuresDraft[String(lot.id)]"
              rows="2"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-[#2d6a45] focus:outline-none focus:ring-2 focus:ring-[#2d6a45]/20"
              :placeholder="lotContractMeasuresPlaceholder(lot)"
            />
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="outline"
              :loading="savingContractMeasures"
              @click="handleSaveContractMeasures"
            >
              Salvar texto das medidas
            </Button>
            <Button
              type="button"
              variant="outline"
              :disabled="savingContractMeasures"
              @click="resetContractMeasuresDraft"
            >
              Restaurar padrão
            </Button>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            :loading="previewingContract"
            @click="handlePreviewContract"
          >
            <EyeIcon class="mr-2 h-4 w-4" />
            Pré-visualizar (minuta)
          </Button>
          <Button
            type="button"
            variant="primary"
            :loading="downloadingContract"
            @click="handleDownloadContract"
          >
            <DocumentArrowDownIcon class="mr-2 h-4 w-4" />
            Baixar contrato
          </Button>
          <Button
            v-if="financingInstallments.length"
            type="button"
            variant="outline"
            :loading="downloadingCarne"
            title="Promissória para impressão"
            @click="handleDownloadCarne"
          >
            <DocumentTextIcon class="mr-2 h-4 w-4" />
            Imprimir Promissória
          </Button>
          <Button
            v-if="showCarnePreview && financingInstallments.length"
            type="button"
            variant="outline"
            @click="openCarnePreview"
          >
            <EyeIcon class="mr-2 h-4 w-4" />
            Preview HTML
          </Button>
          <button
            v-if="financingInstallments.length && !sale.efi_carnet_id"
            type="button"
            :disabled="generatingCarne"
            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
            @click="openCarneModal"
          >
            <BanknotesIcon class="h-4 w-4" />
            {{ generatingCarne ? 'Gerando...' : 'Gerar carnê bancário' }}
          </button>
          <a
            v-else-if="sale.efi_carnet_pdf"
            :href="sale.efi_carnet_pdf"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
          >
            <DocumentArrowDownIcon class="h-4 w-4" />
            Baixar carnê bancário
          </a>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-5">
          <p class="mb-2 text-sm font-medium text-slate-700">Contrato assinado</p>
          <p class="mb-3 text-xs text-slate-500">
            Anexe o contrato assinado (PDF ou foto) para vincular a esta venda.
          </p>

          <div
            v-if="sale.has_signed_contract"
            class="flex flex-col gap-3 rounded-lg border border-[#e8dcc8] bg-[#faf5ee] px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex min-w-0 items-center gap-2">
              <DocumentCheckIcon class="h-5 w-5 shrink-0 text-[#2d6a45]" />
              <span class="truncate text-sm text-[#1c0a06]">
                {{ sale.signed_contract_original_name || 'Contrato assinado anexado' }}
              </span>
            </div>
            <div class="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                :loading="downloadingSigned"
                @click="handleDownloadSignedContract"
              >
                Baixar anexo
              </Button>
              <Button
                type="button"
                variant="outline"
                :loading="uploadingSigned"
                @click="openFilePicker"
              >
                Substituir arquivo
              </Button>
            </div>
          </div>

          <div
            v-else
            class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4"
          >
            <p v-if="selectedFileName" class="mb-3 text-sm text-slate-700">
              Arquivo selecionado: <span class="font-medium">{{ selectedFileName }}</span>
            </p>
            <div class="flex flex-wrap gap-2">
              <Button type="button" variant="outline" @click="openFilePicker">
                <ArrowUpTrayIcon class="mr-2 h-4 w-4" />
                Selecionar arquivo
              </Button>
              <Button
                v-if="selectedFile"
                type="button"
                variant="primary"
                :loading="uploadingSigned"
                @click="handleUploadSignedContract"
              >
                Enviar contrato assinado
              </Button>
            </div>
            <p class="mt-2 text-xs text-slate-400">PDF, JPG, PNG ou WebP — máximo 10 MB</p>
          </div>

          <input
            ref="fileInputRef"
            type="file"
            class="sr-only"
            accept=".pdf,image/jpeg,image/png,image/webp"
            @change="onFileSelected"
          />
        </div>

        <div class="mt-5 border-t border-slate-100 pt-5">
          <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <p class="text-sm font-medium text-slate-700">Notificações WhatsApp</p>
            <div class="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                :loading="sendingWelcomeWhatsapp"
                :disabled="!sale?.client?.phone"
                @click="handleResendWelcomeWhatsapp"
              >
                <ChatBubbleLeftRightIcon class="mr-2 h-4 w-4" />
                Reenviar boas-vindas
              </Button>
              <Button
                v-if="financingOverdueCount > 0"
                type="button"
                variant="outline"
                :loading="sendingOverdueWhatsapp"
                :disabled="!sale?.client?.phone"
                @click="handleSendOverdueWhatsapp"
              >
                <ChatBubbleLeftRightIcon class="mr-2 h-4 w-4" />
                Enviar cobrança de atraso
              </Button>
            </div>
          </div>
          <p
            v-if="!sale?.client?.phone"
            class="mb-3 text-xs text-amber-700"
          >
            Cadastre o WhatsApp do cliente para enviar notificações.
          </p>
          <dl class="grid gap-3 sm:grid-cols-2">
            <div>
              <dt class="text-xs text-slate-500">WhatsApp do cliente</dt>
              <dd class="mt-0.5 text-sm text-slate-800">
                <a
                  v-if="sale.client?.phone && clientWhatsAppUrl"
                  :href="clientWhatsAppUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-action hover:underline"
                >
                  {{ formatPhone(sale.client.phone) }}
                </a>
                <span v-else-if="sale.client?.phone">{{ formatPhone(sale.client.phone) }}</span>
                <span v-else class="text-amber-700">Não cadastrado</span>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Boas-vindas</dt>
              <dd class="mt-0.5 text-sm text-slate-800">
                {{ sale.whatsapp_welcome_sent_at ? formatDateTime(sale.whatsapp_welcome_sent_at) : 'Não enviada' }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Última notificação</dt>
              <dd class="mt-0.5 text-sm text-slate-800">
                {{ sale.whatsapp_last_notification_at ? formatDateTime(sale.whatsapp_last_notification_at) : '—' }}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="card p-4">
          <p class="text-xs text-slate-500">Valor Total</p>
          <p class="text-lg font-bold text-slate-800">{{ formatCurrency(sale.total_value) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-slate-500">Entrada</p>
          <p class="text-lg font-bold text-slate-800">{{ formatCurrency(sale.down_payment) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-slate-500">Pagamento</p>
          <p class="text-lg font-bold text-slate-800">
            <template v-if="sale.installments_count > 0">
              {{ sale.installments_count }}x {{ formatCurrency(sale.installment_value) }}
            </template>
            <template v-else-if="sale.cash_value">
              À vista · {{ formatCurrency(sale.cash_value) }}
            </template>
            <template v-else>À vista</template>
          </p>
          <p
            v-if="sale.installments_count < 1 && sale.discount_amount > 0"
            class="mt-0.5 text-xs text-slate-500"
          >
            Desconto {{ formatCurrency(sale.discount_amount) }}
            <span v-if="sale.discount_percent"> ({{ formatDiscountPercent(sale.discount_percent) }})</span>
          </p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-slate-500">Status</p>
          <span :class="saleStatusClass(sale.status)" class="rounded-full px-2 py-0.5 text-xs font-semibold">
            {{ saleStatusLabel(sale.status) }}
          </span>
        </div>
      </div>

      <div v-if="downPaymentInstallments.length" class="card overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
          <h3 class="text-sm font-semibold text-slate-700">Entrada negociada</h3>
          <p class="mt-0.5 text-xs text-slate-500">
            Total da entrada: {{ formatCurrency(sale.down_payment) }}
          </p>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">Tipo</th>
              <th class="px-4 py-3 text-left">Vencimento</th>
              <th class="px-4 py-3 text-right">Valor</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Pago em</th>
              <th class="px-4 py-3 text-left">WhatsApp</th>
              <th class="px-4 py-3 text-right">Ação</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="inst in downPaymentInstallments"
              :key="inst.id"
              class="hover:bg-slate-50"
            >
              <td class="px-4 py-3 font-medium text-slate-700">
                {{ installmentTypeLabel(inst.type) }}
              </td>
              <td class="px-4 py-3 text-slate-700">
                <div class="flex items-center gap-1.5">
                  <span>{{ formatDate(inst.due_date) }}</span>
                  <button
                    v-if="installmentDisplayStatus(inst) !== 'paid'"
                    type="button"
                    class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    title="Alterar vencimento"
                    @click="openDueDateModal(inst)"
                  >
                    <PencilIcon class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(inst.value) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="installStatusClass(installmentDisplayStatus(inst))" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                  {{ installStatusLabel(installmentDisplayStatus(inst)) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-500">{{ inst.paid_at ? formatDate(inst.paid_at) : '—' }}</td>
              <td class="px-4 py-3 text-left text-xs">
                <InstallmentWhatsappCell :installment="inst" :sale="sale" />
              </td>
              <td class="px-4 py-3 text-right">
                <InstallmentEfiActions
                  :installment="inst"
                  :downloading-recibo="downloadingReciboId === inst.id"
                  :sending-recibo="sendingReciboId === inst.id"
                  @pay="payInstallment"
                  @open-pix="openPixChargeModal"
                  @open-boleto="openBoletoChargeModal"
                  @download-recibo="handleDownloadRecibo"
                  @send-recibo-whatsapp="handleSendReciboWhatsapp"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="financingInstallments.length" class="card overflow-hidden">
        <button
          type="button"
          class="flex w-full items-center justify-between border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
          @click="installmentsExpanded = !installmentsExpanded"
        >
          <div class="flex min-w-0 items-center gap-2">
            <ChevronDownIcon
              class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
              :class="{ '-rotate-90': !installmentsExpanded }"
            />
            <h3 class="text-sm font-semibold text-slate-700">Parcelas</h3>
            <span
              v-if="financingOverdueCount > 0"
              class="rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="badgeColors.danger"
            >
              {{ financingOverdueCount }} em atraso
            </span>
          </div>
          <span class="shrink-0 text-xs text-slate-400">
            {{ financingInstallments.length }} parcelas
          </span>
        </button>
        <table v-show="installmentsExpanded" class="w-full text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Vencimento</th>
              <th class="px-4 py-3 text-right">Valor</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Pago em</th>
              <th class="px-4 py-3 text-left">WhatsApp</th>
              <th class="px-4 py-3 text-right">Ação</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="inst in financingInstallments" :key="inst.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-slate-400">{{ inst.number }}</td>
              <td class="px-4 py-3 text-slate-700">
                <div class="flex items-center gap-1.5">
                  <span>{{ formatDate(inst.due_date) }}</span>
                  <button
                    v-if="installmentDisplayStatus(inst) !== 'paid'"
                    type="button"
                    class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    title="Alterar vencimento"
                    @click="openDueDateModal(inst)"
                  >
                    <PencilIcon class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-medium text-slate-800">{{ formatCurrency(inst.value) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="installStatusClass(installmentDisplayStatus(inst))" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                  {{ installStatusLabel(installmentDisplayStatus(inst)) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-500">{{ inst.paid_at ? formatDate(inst.paid_at) : '—' }}</td>
              <td class="px-4 py-3 text-left text-xs">
                <InstallmentWhatsappCell :installment="inst" :sale="sale" />
              </td>
              <td class="px-4 py-3 text-right">
                <InstallmentEfiActions
                  :installment="inst"
                  :downloading-recibo="downloadingReciboId === inst.id"
                  :sending-recibo="sendingReciboId === inst.id"
                  @pay="payInstallment"
                  @open-pix="openPixChargeModal"
                  @open-boleto="openBoletoChargeModal"
                  @download-recibo="handleDownloadRecibo"
                  @send-recibo-whatsapp="handleSendReciboWhatsapp"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card mt-4 overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <p class="text-sm font-semibold text-slate-700">Histórico WhatsApp</p>
          <span class="text-xs text-slate-400">{{ interactions.length }} registros</span>
        </div>

        <div v-if="!interactions.length" class="px-4 py-6 text-center text-xs text-slate-400">
          Nenhuma interação registrada ainda.
        </div>

        <div v-else class="divide-y divide-slate-50">
          <div
            v-for="inter in interactions"
            :key="inter.id"
            class="flex items-start gap-3 px-4 py-3"
          >
            <div class="mt-0.5 shrink-0">
              <span
                v-if="inter.direction === 'outbound'"
                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs text-blue-600"
                title="Sistema enviou"
              >↗</span>
              <span
                v-else
                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-xs text-emerald-600"
                title="Cliente respondeu"
              >↙</span>
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                  <p class="text-xs font-semibold text-slate-700">{{ inter.type_label }}</p>
                  <span
                    v-if="inter.installments_label"
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600"
                  >
                    {{ inter.installments_label }}
                  </span>
                </div>
                <p class="shrink-0 text-xs text-slate-400">{{ fmtDate(inter.created_at) }}</p>
              </div>
              <div
                v-if="inter.message"
                class="mt-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600 whitespace-pre-wrap break-words"
                v-html="formatWhatsappHtml(inter.message)"
              />
            </div>
          </div>
        </div>
      </div>

      <DocumentManager
        entity-type="sale"
        :entity-id="sale.id"
        title="Documentos"
        description="Documentos do cliente vinculados a esta venda (cópia congelada no momento da compra) e anexos adicionais."
      />
    </template>

    <InstallmentChargeModal
      :is-open="Boolean(chargeModal)"
      :installment="chargeModal?.installment ?? null"
      :charge-type="chargeModal?.type ?? 'pix'"
      :client-phone="sale?.client?.phone ?? ''"
      :client-name="sale?.client?.name ?? ''"
      :contract-no="saleContractNo()"
      :carnet-pdf-url="sale?.efi_carnet_pdf ?? ''"
      @close="chargeModal = null"
      @updated="handleChargeUpdated"
    />

    <InstallmentPaymentModal
      :is-open="Boolean(paymentModal)"
      :installment="paymentModal?.installment ?? null"
      :installments-count="sale?.installments_count ?? null"
      @close="paymentModal = null"
      @paid="handleInstallmentPaid"
    />

    <InstallmentDueDateModal
      :is-open="Boolean(dueDateModal)"
      :installment="dueDateModal?.installment ?? null"
      :installments-count="sale?.installments_count ?? null"
      @close="dueDateModal = null"
      @updated="handleDueDateUpdated"
    />

    <Modal
      :is-open="carneModalOpen"
      title="Gerar carnê bancário"
      @close="carneModalOpen = false"
    >
      <p class="mb-4 text-sm text-slate-600">
        A Efi exige que a 1ª parcela do carnê vença após hoje.
        Serão geradas {{ unpaidFinancingCount }} parcela(s) em aberto.
      </p>
      <p
        v-if="carneScheduledFirstDueIsPast"
        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
      >
        A 1ª parcela cadastrada ({{ formatDate(scheduledCarneFirstDueDate) }}) já passou.
        Escolha uma nova data de vencimento.
      </p>
      <Flatpickr
        v-model="carneFirstDueDate"
        label="Vencimento da 1ª parcela"
        :min-date="carneMinDueDate"
        placeholder="DD/MM/AAAA"
      />
      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" @click="carneModalOpen = false">
          Cancelar
        </Button>
        <Button
          type="button"
          variant="primary"
          :loading="generatingCarne"
          :disabled="!carneFirstDueDate"
          @click="generateCarne"
        >
          Gerar carnê
        </Button>
      </div>
    </Modal>

    <Modal
      :is-open="changeLotModalOpen"
      title="Trocar lote da venda"
      @close="closeChangeLotModal"
    >
      <p class="mb-3 text-xs text-slate-500">
        Selecione o(s) novo(s) lote(s) no mapa ou na lista abaixo. O valor total e as parcelas
        <strong>ainda pendentes</strong> serão recalculados com base no novo lote — parcelas já pagas não são alteradas.
      </p>

      <div v-if="loadingChangeLotLots" class="py-10 text-center text-xs text-slate-400">
        Carregando lotes do empreendimento...
      </div>

      <template v-else>
        <LotPickerMap
          v-model="changeLotSelectedIds"
          :lots="changeLotDevelopmentLots"
          height="380px"
        />

        <SelectInput
          v-if="changeLotDevelopmentLots.length"
          class="mt-4"
          label="Lote(s) selecionado(s)"
          mode="multiple"
          :model-value="changeLotSelectedIds"
          :options="changeLotLotOptions"
          placeholder="Selecione um ou mais lotes…"
          @update:model-value="changeLotSelectedIds = $event"
        />
      </template>

      <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" @click="closeChangeLotModal">
          Cancelar
        </Button>
        <Button
          type="button"
          variant="primary"
          :loading="changingLot"
          :disabled="loadingChangeLotLots || !changeLotSelectedIds.length"
          @click="handleChangeLot"
        >
          Confirmar troca
        </Button>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import Swal from 'sweetalert2';
import api from '@/services/api';
import {
  downloadContract,
  downloadCarne,
  downloadSignedContract,
  downloadInstallmentRecibo,
  previewContract,
  uploadSignedContract,
  updateContractLotMeasures,
} from '@/services/sale.service';
import { getApiErrorMessage } from '@/utils/apiError';
import {
  resolveLotArea,
  resolveLotContractMeasuresText,
  buildAutoContractMeasuresText,
} from '@/utils/lotMeasures';
import { swalDefaultConfig } from '@/composables/useAlert';
import { formatCpf, formatCurrency, formatPhone } from '@/utils/format';
import {
  badgeColors,
  installmentStatusClass as installmentStatusClassHelper,
  installmentStatusLabel as installmentStatusLabelHelper,
  installmentTypeLabel as installmentTypeLabelHelper,
  saleStatusClass as saleStatusClassHelper,
  saleStatusLabel as saleStatusLabelHelper,
} from '@/utils/status';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import Flatpickr from '@/components/Common/Flatpickr.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import LotPickerMap from '@/components/Common/LotPickerMap.vue';
import DocumentManager from '@/components/Common/DocumentManager.vue';
import InstallmentWhatsappCell from '@/components/Sales/InstallmentWhatsappCell.vue';
import InstallmentEfiActions from '@/components/Sales/InstallmentEfiActions.vue';
import InstallmentChargeModal from '@/components/Sales/InstallmentChargeModal.vue';
import InstallmentPaymentModal from '@/components/Sales/InstallmentPaymentModal.vue';
import InstallmentDueDateModal from '@/components/Sales/InstallmentDueDateModal.vue';
import { formatBrazilWhatsappNumber, installmentDisplayStatus } from '@/utils/whatsapp';
import { formatWhatsappHtml } from '@/utils/whatsappFormat';
import {
  ArrowLeftIcon,
  ArrowUpTrayIcon,
  BanknotesIcon,
  ChevronDownIcon,
  ChatBubbleLeftRightIcon,
  DocumentArrowDownIcon,
  DocumentCheckIcon,
  DocumentTextIcon,
  EyeIcon,
  PencilIcon,
} from '@heroicons/vue/24/outline';

const saleStatusClass = (status) => saleStatusClassHelper(status);
const saleStatusLabel = (status) => saleStatusLabelHelper(status);
const showCarnePreview = import.meta.env.DEV;
const installStatusClass = (status) => installmentStatusClassHelper(status);
const installStatusLabel = (status) => installmentStatusLabelHelper(status);
const installmentTypeLabel = (type) => installmentTypeLabelHelper(type);

const downPaymentInstallments = computed(() =>
  (sale.value?.installments ?? []).filter((inst) => inst.type === 'down_payment'),
);

const financingInstallments = computed(() =>
  (sale.value?.installments ?? []).filter((inst) => inst.type !== 'down_payment'),
);

const financingOverdueCount = computed(() =>
  financingInstallments.value.filter(
    (inst) => installmentDisplayStatus(inst) === 'overdue',
  ).length,
);

const unpaidFinancingInstallments = computed(() =>
  financingInstallments.value.filter((inst) => inst.status !== 'paid'),
);

const unpaidFinancingCount = computed(() => unpaidFinancingInstallments.value.length);

function toApiDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const carneMinDueDate = computed(() => {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(0, 0, 0, 0);
  return toApiDate(tomorrow);
});

const scheduledCarneFirstDueDate = computed(() => {
  const firstUnpaid = unpaidFinancingInstallments.value[0];
  return firstUnpaid?.due_date ?? sale.value?.first_due_date ?? '';
});

const suggestedCarneFirstDueDate = computed(() => {
  const scheduled = scheduledCarneFirstDueDate.value;
  if (!scheduled) {
    return carneMinDueDate.value;
  }

  return scheduled >= carneMinDueDate.value ? scheduled : carneMinDueDate.value;
});

const carneScheduledFirstDueIsPast = computed(() => {
  const scheduled = scheduledCarneFirstDueDate.value;
  if (!scheduled) {
    return false;
  }

  return scheduled < carneMinDueDate.value;
});

const clientWhatsAppUrl = computed(() => {
  const phone = sale.value?.client?.phone;
  if (!phone) {
    return '';
  }
  const number = formatBrazilWhatsappNumber(phone);
  return number ? `https://wa.me/${number}` : '';
});

// Venda com lotes vizinhos (ex.: 2 lotes comprados juntos) — 1 contrato/carnê
// cobre todos os lotes em `sale.lots`, então a UI lista todos em vez de só o
// lote "primário" (`sale.lot`).
const temMultiplosLotes = computed(() => (sale.value?.lots?.length ?? 0) > 1);

const installmentsExpanded = ref(false);

const route = useRoute();
const router = useRouter();
const toast = useToast();
const sale = ref(null);
const interactions = ref([]);
const loading = ref(false);
const previewingContract = ref(false);
const downloadingContract = ref(false);
const savingContractMeasures = ref(false);
const contractMeasuresDraft = ref({});

const contractLots = computed(() => {
  if (sale.value?.lots?.length) {
    return sale.value.lots;
  }

  return sale.value?.lot ? [sale.value.lot] : [];
});

const lotesAreaTotal = computed(() =>
  contractLots.value.reduce((sum, lote) => sum + (resolveLotArea(lote) || 0), 0),
);

function lotContractLabel(lot) {
  const block = lot.block || lot.zone?.name;
  return block ? `${block} · Lote ${lot.number}` : `Lote ${lot.number}`;
}

function lotContractMeasuresPlaceholder(lot) {
  return buildAutoContractMeasuresText(lot)
    || 'Ex.: com área total de 622m², medindo Frente de 16m…';
}

function syncContractMeasuresDraft() {
  const overrides = sale.value?.contract_lot_measures ?? {};
  const draft = {};

  for (const lot of contractLots.value) {
    const key = String(lot.id);
    const override = overrides[key] ?? overrides[lot.id] ?? null;
    draft[key] = resolveLotContractMeasuresText(lot, override) ?? '';
  }

  contractMeasuresDraft.value = draft;
}

function resetContractMeasuresDraft() {
  const draft = {};

  for (const lot of contractLots.value) {
    draft[String(lot.id)] = lot.contract_measures_text?.trim()
      || buildAutoContractMeasuresText(lot)
      || '';
  }

  contractMeasuresDraft.value = draft;
}

async function persistContractMeasuresIfNeeded() {
  const payload = {};

  for (const lot of contractLots.value) {
    const key = String(lot.id);
    const text = String(contractMeasuresDraft.value[key] ?? '').trim();
    const auto = resolveLotContractMeasuresText(lot, null) ?? '';

    if (text && text !== auto) {
      payload[key] = text;
    }
  }

  const current = sale.value?.contract_lot_measures ?? {};
  const currentKeys = Object.keys(current);
  const nextKeys = Object.keys(payload);
  const unchanged = currentKeys.length === nextKeys.length
    && nextKeys.every((key) => current[key] === payload[key]);

  if (unchanged) {
    return;
  }

  const updated = await updateContractLotMeasures(sale.value.id, payload);
  sale.value = { ...sale.value, ...updated };
}

async function handleSaveContractMeasures() {
  savingContractMeasures.value = true;
  try {
    await persistContractMeasuresIfNeeded();
    toast.success('Texto das medidas salvo.');
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao salvar texto das medidas.'));
  } finally {
    savingContractMeasures.value = false;
  }
}

watch(
  () => sale.value?.id,
  () => {
    if (sale.value) {
      syncContractMeasuresDraft();
    }
  },
);

const downloadingCarne = ref(false);
const downloadingSigned = ref(false);
const uploadingSigned = ref(false);
const fileInputRef = ref(null);
const selectedFile = ref(null);
const selectedFileName = ref('');
const generatingCarne = ref(false);
const carneModalOpen = ref(false);
const carneFirstDueDate = ref('');
const sendingOverdueWhatsapp = ref(false);
const sendingWelcomeWhatsapp = ref(false);
const cancellingSale = ref(false);
const chargeModal = ref(null);
const downloadingReciboId = ref(null);
const sendingReciboId = ref(null);
const paymentModal = ref(null);
const dueDateModal = ref(null);
const carneData = ref(null);

const changeLotModalOpen = ref(false);
const changeLotDevelopmentLots = ref([]);
const changeLotSelectedIds = ref([]);
const loadingChangeLotLots = ref(false);
const changingLot = ref(false);

const changeLotLotOptions = computed(() => {
  const selected = changeLotSelectedIds.value.map(String);

  return changeLotDevelopmentLots.value
    .filter((l) => l.status === 'available' || selected.includes(String(l.id)))
    .slice()
    .sort((a, b) => String(a.block ?? '').localeCompare(String(b.block ?? '')) || String(a.number).localeCompare(String(b.number)))
    .map((l) => ({
      value: String(l.id),
      label: `Q${l.block ?? '?'} · L${l.number}${l.area ? ' · ' + l.area + 'm²' : ''}`,
    }));
});

const showRegistrationSuccess = computed(() => route.query.registered === '1');

const formatDate = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('pt-BR') : '—');
const formatDiscountPercent = (value) => `${String(value).replace('.', ',')}%`;

function formatDateTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const fmtDate = formatDateTime;

async function loadSale() {
  loading.value = true;
  try {
    const { data } = await api.get(`/sales/${route.params.id}`);
    sale.value = data.data ?? data;
  } catch {
    toast.error('Erro ao carregar venda');
    router.push({ name: 'sales.index' });
  } finally {
    loading.value = false;
  }
}

async function loadInteractions() {
  try {
    const { data } = await api.get(`/sales/${route.params.id}/interactions`);
    interactions.value = data.data ?? data;
  } catch {
    interactions.value = [];
  }
}

async function handleSendOverdueWhatsapp() {
  if (!sale.value?.client?.phone) {
    toast.warning('Cliente sem telefone/WhatsApp cadastrado.');
    return;
  }

  const phone = sale.value.client.phone;
  const result = await Swal.fire({
    ...swalDefaultConfig,
    title: 'Enviar cobrança de atraso?',
    html: `
      <div class="text-left text-sm text-slate-600 space-y-2">
        <p>Será enviada a <strong>mesma mensagem interativa</strong> do aviso automático (opções 1, 2 e 3), para:</p>
        <p class="font-medium text-slate-800">${phone}</p>
        <p class="text-xs text-slate-500 pt-2 border-t border-slate-100">
          Pode reenviar mesmo se o sistema já tiver notificado — útil após atualizar o telefone ou para reforçar a cobrança.
        </p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Enviar agora',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) {
    return;
  }

  sendingOverdueWhatsapp.value = true;
  try {
    const { data } = await api.post(`/sales/${sale.value.id}/whatsapp/overdue`);
    toast.success(data.message || 'Cobrança enviada com sucesso.');
    await loadSale();
    await loadInteractions();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Não foi possível enviar a cobrança.'));
  } finally {
    sendingOverdueWhatsapp.value = false;
  }
}

async function handleResendWelcomeWhatsapp() {
  if (!sale.value?.client?.phone) {
    toast.warning('Cliente sem telefone/WhatsApp cadastrado.');
    return;
  }

  const phone = sale.value.client.phone;
  const result = await Swal.fire({
    ...swalDefaultConfig,
    title: 'Reenviar boas-vindas?',
    html: `
      <div class="text-left text-sm text-slate-600 space-y-2">
        <p>Será enviada a <strong>mesma mensagem de boas-vindas</strong> da venda, para:</p>
        <p class="font-medium text-slate-800">${phone}</p>
        <p class="text-xs text-slate-500 pt-2 border-t border-slate-100">
          Pode reenviar mesmo se já tiver sido enviada antes — útil depois de corrigir o número do cliente.
        </p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Enviar agora',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) {
    return;
  }

  sendingWelcomeWhatsapp.value = true;
  try {
    const { data } = await api.post(`/sales/${sale.value.id}/whatsapp/welcome`);
    toast.success(data.message || 'Mensagem de boas-vindas reenviada com sucesso.');
    await loadSale();
    await loadInteractions();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Não foi possível reenviar a mensagem de boas-vindas.'));
  } finally {
    sendingWelcomeWhatsapp.value = false;
  }
}

async function handleCancelSale() {
  const result = await Swal.fire({
    ...swalDefaultConfig,
    title: 'Cancelar venda?',
    html: `
      <div class="text-left text-sm text-slate-600 space-y-2">
        <p>${temMultiplosLotes.value ? 'Os lotes serão liberados' : 'O lote será liberado'} automaticamente para <strong>Disponível</strong> e as parcelas pendentes serão marcadas como canceladas.</p>
        <p class="text-xs text-slate-500 pt-2 border-t border-slate-100">
          Parcelas já pagas, recibos e documentos da venda são preservados.
        </p>
      </div>
    `,
    icon: 'warning',
    input: 'textarea',
    inputLabel: 'Motivo do cancelamento',
    inputPlaceholder: 'Descreva o motivo do cancelamento...',
    inputValidator: (value) => {
      if (!value || value.trim().length < 3) {
        return 'Informe o motivo do cancelamento (mínimo 3 caracteres).';
      }
      return undefined;
    },
    showCancelButton: true,
    confirmButtonText: 'Cancelar venda',
    cancelButtonText: 'Voltar',
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) {
    return;
  }

  cancellingSale.value = true;
  try {
    const { data } = await api.post(`/sales/${sale.value.id}/cancel`, {
      reason: result.value,
    });
    sale.value = data.data ?? data;
    toast.success('Venda cancelada com sucesso.');
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Não foi possível cancelar a venda.'));
  } finally {
    cancellingSale.value = false;
  }
}

async function openChangeLotModal() {
  const developmentId = sale.value?.lot?.development?.id ?? sale.value?.lot?.development_id;

  if (!developmentId) {
    toast.error('Não foi possível identificar o empreendimento desta venda.');
    return;
  }

  const currentLots = sale.value?.lots?.length ? sale.value.lots : [sale.value?.lot].filter(Boolean);
  changeLotSelectedIds.value = currentLots.map((l) => String(l.id));
  changeLotDevelopmentLots.value = [];
  changeLotModalOpen.value = true;
  loadingChangeLotLots.value = true;

  try {
    const { data } = await api.get(`/developments/${developmentId}/lots`, { params: { all: 1 } });
    changeLotDevelopmentLots.value = data.data ?? data;
  } catch {
    toast.error('Erro ao carregar lotes do empreendimento.');
    changeLotModalOpen.value = false;
  } finally {
    loadingChangeLotLots.value = false;
  }
}

function closeChangeLotModal() {
  changeLotModalOpen.value = false;
}

async function handleChangeLot() {
  if (!changeLotSelectedIds.value.length) {
    toast.warning('Selecione ao menos um lote.');
    return;
  }

  const result = await Swal.fire({
    ...swalDefaultConfig,
    title: 'Confirmar troca de lote?',
    html: `
      <div class="text-left text-sm text-slate-600 space-y-2">
        <p>${temMultiplosLotes.value ? 'Os lotes anteriores serão liberados' : 'O lote anterior será liberado'} e o(s) novo(s) será(ão) marcado(s) como <strong>Vendido</strong>.</p>
        <p>O valor total e as parcelas <strong>ainda pendentes</strong> serão recalculados com base no novo lote.</p>
        <p class="text-xs text-slate-500 pt-2 border-t border-slate-100">Parcelas já pagas não são alteradas.</p>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Confirmar troca',
    cancelButtonText: 'Voltar',
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) {
    return;
  }

  changingLot.value = true;
  try {
    const { data } = await api.post(`/sales/${sale.value.id}/change-lot`, {
      lot_ids: changeLotSelectedIds.value.map((id) => Number(id)),
    });
    sale.value = data.data ?? data;
    toast.success('Lote da venda atualizado com sucesso.');
    changeLotModalOpen.value = false;
    await loadInteractions();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Não foi possível trocar o lote desta venda.'));
  } finally {
    changingLot.value = false;
  }
}

async function handlePreviewContract() {
  previewingContract.value = true;
  try {
    await persistContractMeasuresIfNeeded();
    await previewContract(sale.value.id);
  } catch (err) {
    if (err?.code === 'popup_blocked') {
      toast.warning('Permita pop-ups para visualizar ou use "Baixar contrato".');
    } else {
      toast.error(getApiErrorMessage(err, 'Erro ao abrir pré-visualização do contrato.'));
    }
  } finally {
    previewingContract.value = false;
  }
}

async function handleDownloadContract() {
  downloadingContract.value = true;
  try {
    await persistContractMeasuresIfNeeded();
    await downloadContract(sale.value.id);
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao baixar contrato.'));
  } finally {
    downloadingContract.value = false;
  }
}

async function handleDownloadCarne() {
  downloadingCarne.value = true;
  try {
    await downloadCarne(sale.value.id);
  } catch {
    toast.error('Erro ao baixar promissória.');
  } finally {
    downloadingCarne.value = false;
  }
}

function openCarnePreview() {
  const previewRoute = router.resolve({
    name: 'sales.carne.preview',
    params: { id: sale.value.id },
  });

  window.open(previewRoute.href, '_blank');
}

function openFilePicker() {
  fileInputRef.value?.click();
}

function onFileSelected(event) {
  const file = event.target.files?.[0];
  if (!file) {
    return;
  }
  selectedFile.value = file;
  selectedFileName.value = file.name;
  if (sale.value?.has_signed_contract) {
    handleUploadSignedContract();
  }
}

async function handleUploadSignedContract() {
  if (!selectedFile.value) {
    toast.warning('Selecione o arquivo do contrato assinado.');
    return;
  }

  uploadingSigned.value = true;
  try {
    const updated = await uploadSignedContract(sale.value.id, selectedFile.value);
    sale.value = updated;
    selectedFile.value = null;
    selectedFileName.value = '';
    if (fileInputRef.value) {
      fileInputRef.value.value = '';
    }
    toast.success('Contrato assinado anexado com sucesso.');
    clearRegistrationQuery();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao enviar contrato assinado.'));
  } finally {
    uploadingSigned.value = false;
  }
}

async function handleDownloadSignedContract() {
  downloadingSigned.value = true;
  try {
    await downloadSignedContract(
      sale.value.id,
      sale.value.signed_contract_original_name,
    );
  } catch {
    toast.error('Erro ao baixar contrato assinado.');
  } finally {
    downloadingSigned.value = false;
  }
}

function clearRegistrationQuery() {
  if (route.query.registered === '1') {
    router.replace({ name: 'sales.show', params: { id: route.params.id } });
  }
}

function payInstallment(inst) {
  paymentModal.value = { installment: inst };
}

async function handleInstallmentPaid(updatedInstallment) {
  paymentModal.value = null;

  if (updatedInstallment) {
    patchInstallmentInSale(updatedInstallment);
  }

  await loadSale();
  await loadInteractions();
}

function openPixChargeModal(installment) {
  chargeModal.value = { installment, type: 'pix' };
}

function openBoletoChargeModal(installment) {
  chargeModal.value = { installment, type: 'boleto' };
}

function openDueDateModal(installment) {
  dueDateModal.value = { installment };
}

async function handleDueDateUpdated(updatedInstallment) {
  dueDateModal.value = null;

  if (updatedInstallment) {
    patchInstallmentInSale(updatedInstallment);
  }

  await loadSale();
}

async function handleDownloadRecibo(inst) {
  if (downloadingReciboId.value) {
    return;
  }

  downloadingReciboId.value = inst.id;
  try {
    await downloadInstallmentRecibo(inst.id);
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Erro ao baixar o recibo.'));
  } finally {
    downloadingReciboId.value = null;
  }
}

async function handleSendReciboWhatsapp(inst) {
  if (sendingReciboId.value) {
    return;
  }

  sendingReciboId.value = inst.id;
  try {
    const { data } = await api.post(`/installments/${inst.id}/recibo/whatsapp`);

    if (data?.warning) {
      toast.warning(data.warning);
    } else {
      toast.success('Recibo enviado pelo WhatsApp.');
    }

    await loadInteractions();
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Não foi possível enviar o recibo pelo WhatsApp.'));
  } finally {
    sendingReciboId.value = null;
  }
}

function patchInstallmentInSale(updatedInstallment) {
  if (!updatedInstallment?.id || !sale.value?.installments) {
    return;
  }

  sale.value = {
    ...sale.value,
    installments: sale.value.installments.map((inst) => (
      inst.id === updatedInstallment.id
        ? { ...inst, ...updatedInstallment }
        : inst
    )),
  };
}

async function handleChargeUpdated(updatedInstallment) {
  if (updatedInstallment) {
    patchInstallmentInSale(updatedInstallment);

    if (chargeModal.value?.installment?.id === updatedInstallment.id) {
      chargeModal.value = {
        ...chargeModal.value,
        installment: updatedInstallment,
      };
    }
  }

  await loadInteractions();
}

async function openCarneModal() {
  carneFirstDueDate.value = suggestedCarneFirstDueDate.value;
  carneModalOpen.value = true;
}

async function generateCarne() {
  if (!carneFirstDueDate.value) {
    toast.warning('Informe o vencimento da 1ª parcela.');
    return;
  }

  generatingCarne.value = true;

  try {
    const { data } = await api.post(`/sales/${route.params.id}/efi/carne`, {
      first_due_date: carneFirstDueDate.value,
    });
    carneData.value = data;
    carneModalOpen.value = false;

    const opened = data.pdf_carnet
      ? window.open(data.pdf_carnet, '_blank', 'noopener,noreferrer') !== null
      : false;

    const dueLabel = data.first_due_date ? formatDate(data.first_due_date) : '';

    if (!opened) {
      toast.warning(
        dueLabel
          ? `Carnê gerado com 1ª parcela em ${dueLabel}. Use o link "Baixar carnê bancário".`
          : 'Carnê bancário gerado. Use o link "Baixar carnê bancário" se a aba não abriu.',
      );
    } else if (data.adjusted_from_scheduled) {
      toast.success(`Carnê gerado — ${data.charges} parcelas. 1ª parcela ajustada para ${dueLabel}.`);
    } else {
      toast.success(`Carnê bancário gerado — ${data.charges} parcelas.`);
    }

    await loadSale();
  } catch (err) {
    toast.error(err?.response?.data?.error ?? 'Erro ao gerar carnê bancário.');
  } finally {
    generatingCarne.value = false;
  }
}

function saleContractNo() {
  const saleDate = sale.value?.sale_date ?? '';
  const year = saleDate ? saleDate.slice(0, 4) : new Date().getFullYear();
  return `${String(sale.value?.id ?? '').padStart(4, '0')}/${year}`;
}

onMounted(() => {
  loadSale();
  loadInteractions();
});
</script>
