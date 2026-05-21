<script setup>
import { computed } from 'vue';
import Swal from 'sweetalert2';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import { swalDefaultConfig } from '@/composables/useAlert';
import { useSettingsStore } from '@/stores/settings';
import { formatCurrency } from '@/utils/format';
import {
  buildManualOverdueMessage,
  buildWhatsAppUrl,
  DEFAULT_MANUAL_OVERDUE_MESSAGE,
  installmentDisplayStatus,
} from '@/utils/whatsapp';

const props = defineProps({
  installment: {
    type: Object,
    required: true,
  },
  sale: {
    type: Object,
    required: true,
  },
});

const settingsStore = useSettingsStore();

const manualMessageTemplate = computed(
  () => settingsStore.publicConfig?.whatsapp_manual_overdue_message
    || DEFAULT_MANUAL_OVERDUE_MESSAGE,
);

const isOverdue = computed(
  () => installmentDisplayStatus(props.installment) === 'overdue',
);

const hasPhone = computed(() => Boolean(props.sale?.client?.phone));

const notificationLines = computed(() => {
  const lines = [];
  if (props.installment.whatsapp_reminder_sent_at) {
    lines.push({
      label: 'Lembrete',
      at: formatDateTime(props.installment.whatsapp_reminder_sent_at),
    });
  }
  if (props.installment.whatsapp_overdue_sent_at) {
    lines.push({
      label: 'Atraso',
      at: formatDateTime(props.installment.whatsapp_overdue_sent_at),
    });
  }
  return lines;
});

function formatDate(value) {
  return value ? new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR') : '—';
}

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

function contractNo() {
  const saleDate = props.sale.sale_date ?? '';
  const year = saleDate ? saleDate.slice(0, 4) : new Date().getFullYear();
  return `${String(props.sale.id).padStart(4, '0')}/${year}`;
}

function automaticNotificationHtml() {
  if (!notificationLines.value.length) {
    return '<p class="text-slate-500">Nenhuma notificação automática registrada para esta parcela.</p>';
  }

  return `<ul class="list-disc pl-5 space-y-1">${notificationLines.value
    .map((line) => `<li><strong>${line.label}:</strong> ${line.at}</li>`)
    .join('')}</ul>`;
}

async function startManualChat() {
  if (!hasPhone.value) {
    await Swal.fire({
      ...swalDefaultConfig,
      title: 'Telefone não cadastrado',
      text: 'Este cliente não possui WhatsApp/telefone no cadastro.',
      icon: 'warning',
      confirmButtonText: 'OK',
    });
    return;
  }

  const label = props.installment.type === 'down_payment'
    ? 'Entrada'
    : `Parcela ${props.installment.number}`;

  const result = await Swal.fire({
    ...swalDefaultConfig,
    title: 'Iniciar conversa no WhatsApp',
    html: `
      <div class="text-left text-sm text-slate-600 space-y-3">
        <p>Antes de abrir o WhatsApp, confira as <strong>notificações automáticas</strong> já enviadas para <strong>${label}</strong>:</p>
        ${automaticNotificationHtml()}
        <p class="text-xs text-slate-500 pt-1 border-t border-slate-100">
          Você será redirecionado para o WhatsApp do cliente.
        </p>
      </div>
    `,
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Abrir WhatsApp',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) {
    return;
  }

  const message = buildManualOverdueMessage({
    clientName: props.sale.client.name,
    contractNo: contractNo(),
    installment: props.installment,
    formatDate,
    formatCurrency,
    template: manualMessageTemplate.value,
  });

  const url = buildWhatsAppUrl(props.sale.client.phone, message);
  if (!url) {
    await Swal.fire({
      ...swalDefaultConfig,
      title: 'Número inválido',
      text: 'Não foi possível montar o link do WhatsApp.',
      icon: 'error',
      confirmButtonText: 'OK',
    });
    return;
  }

  window.open(url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
  <div class="space-y-1.5">
    <template v-if="notificationLines.length">
      <p
        v-for="(line, idx) in notificationLines"
        :key="idx"
        class="text-slate-600"
      >
        {{ line.label }}: {{ line.at }}
      </p>
    </template>
    <span v-else class="text-slate-400">—</span>

    <button
      v-if="isOverdue && hasPhone"
      type="button"
      class="inline-flex items-center gap-1 text-xs font-medium text-action hover:text-action-hover hover:underline"
      @click="startManualChat"
    >
      <ChatBubbleLeftRightIcon class="h-4 w-4 shrink-0" />
      Conversar
    </button>
  </div>
</template>
