import { ref, watch } from 'vue';
import api from '@/services/api';
import { formatCep, formatCpf, formatPhone } from '@/utils/format';
import { getCpfValidationMessage, isValidEmail } from '@/utils/validation';

export const defaultClientForm = () => ({
  name: '',
  cpf: '',
  rg: '',
  rg_issuer: '',
  birth_date: '',
  profession: '',
  marital_status: '',
  phone: '',
  email: '',
  zip_code: '',
  address: '',
  address_number: '',
  neighborhood: '',
  city: 'Cafarnaum',
  state: 'BA',
  notes: '',
});

export function useClientForm() {
  const form = ref(defaultClientForm());
  const errors = ref({});

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
  let lastFetchedZip = '';

  const skipPhoneWatch = ref(false);

  function clearErrors() {
    errors.value = {};
  }

  function setFieldError(field, message) {
    errors.value = { ...errors.value, [field]: message };
  }

  function applyClientData(client) {
    skipPhoneWatch.value = true;
    const base = defaultClientForm();
    Object.keys(base).forEach((key) => {
      base[key] = client[key] ?? base[key];
    });
    if (client.cpf) base.cpf = formatCpf(client.cpf);
    if (client.phone) base.phone = formatPhone(client.phone);
    if (client.zip_code) base.zip_code = formatCep(client.zip_code);
    lastFetchedZip = base.zip_code.replace(/\D/g, '');
    form.value = base;

    if (client.whatsapp_status === 'confirmed') {
      whatsappStatus.value = 'has';
      otpVerified.value = true;
    } else if (client.whatsapp_status === 'none') {
      whatsappStatus.value = 'no';
    } else {
      whatsappStatus.value = null;
      otpVerified.value = false;
    }
    whatsappManual.value = false;
    resetOtp();
    skipPhoneWatch.value = false;
  }

  function clearFieldError(field) {
    if (!errors.value[field]) return;
    const next = { ...errors.value };
    delete next[field];
    errors.value = next;
  }

  function onStateInput(value) {
    form.value.state = String(value).toUpperCase().slice(0, 2);
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
    if (logradouro) form.value.address = logradouro;
    if (bairro) form.value.neighborhood = bairro;
    if (cidade) form.value.city = cidade;
    if (estado) form.value.state = estado;
  }

  function resetOtp() {
    otpSent.value = false;
    otpCode.value = '';
    otpVerified.value = false;
    otpError.value = '';
    otpCountdown.value = 0;
    clearInterval(otpTimer);
  }

  function startOtpCountdown() {
    otpCountdown.value = 60;
    clearInterval(otpTimer);
    otpTimer = setInterval(() => {
      otpCountdown.value -= 1;
      if (otpCountdown.value <= 0) clearInterval(otpTimer);
    }, 1000);
  }

  async function checkWhatsapp() {
    const raw = form.value.phone.replace(/\D/g, '');
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

  async function sendOtp() {
    const raw = form.value.phone.replace(/\D/g, '');
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
        phone: form.value.phone.replace(/\D/g, ''),
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

  function onOtpInput(value) {
    otpCode.value = String(value).replace(/\D/g, '').slice(0, 4);
  }

  function validate() {
    clearErrors();
    let valid = true;

    if (!form.value.name?.trim()) {
      setFieldError('name', 'Nome é obrigatório.');
      valid = false;
    }

    const cpfMsg = getCpfValidationMessage(form.value.cpf, { required: true });
    if (cpfMsg) {
      setFieldError('cpf', cpfMsg);
      valid = false;
    }

    const phoneDigits = form.value.phone?.replace(/\D/g, '') ?? '';
    if (phoneDigits && phoneDigits.length < 10) {
      setFieldError('phone', 'Telefone incompleto.');
      valid = false;
    }

    if (!isValidEmail(form.value.email)) {
      setFieldError('email', 'E-mail inválido.');
      valid = false;
    }

    if (form.value.state && form.value.state.length !== 2) {
      setFieldError('state', 'Use a sigla do estado (ex.: BA).');
      valid = false;
    }

    return valid;
  }

  function getPayload() {
    return { ...form.value };
  }

  async function resolveWhatsappStatus(clientId) {
    if (otpVerified.value || whatsappManual.value) {
      await saveWhatsappStatus(clientId, 'confirmed');
    } else if (whatsappStatus.value === 'no') {
      await saveWhatsappStatus(clientId, 'none');
    }
  }

  async function saveWhatsappStatus(clientId, status) {
    try {
      await api.post(`/clients/${clientId}/whatsapp-status`, { status });
    } catch {
      // não bloqueia o cadastro
    }
  }

  function resetForm() {
    form.value = defaultClientForm();
    lastFetchedZip = '';
    clearErrors();
    erroCep.value = '';
    whatsappStatus.value = null;
    whatsappManual.value = false;
    resetOtp();
  }

  watch(
    () => form.value.phone,
    (value, previous) => {
      if (skipPhoneWatch.value || value === previous) return;
      whatsappStatus.value = null;
      otpVerified.value = false;
      resetOtp();
      clearFieldError('phone');
    },
  );

  watch(
    () => form.value.zip_code,
    (value) => {
      const digits = String(value ?? '').replace(/\D/g, '');
      if (digits.length < 8) {
        lastFetchedZip = '';
        erroCep.value = '';
      }
    },
  );

  return {
    form,
    errors,
    buscandoCep,
    erroCep,
    whatsappStatus,
    whatsappManual,
    otpSent,
    otpCode,
    otpVerified,
    otpError,
    otpSending,
    otpVerifying,
    otpCountdown,
    applyClientData,
    onStateInput,
    onOtpInput,
    checkWhatsapp,
    sendOtp,
    verifyOtp,
    resetOtp,
    buscarCep,
    validate,
    getPayload,
    resolveWhatsappStatus,
    resetForm,
  };
}
