import api from './api';

export async function getContractBlob(saleId) {
  const { data } = await api.get(`/sales/${saleId}/contract`, { responseType: 'blob' });
  return data;
}

export async function getContractPreviewBlob(saleId) {
  const { data } = await api.get(`/sales/${saleId}/contract/preview`, { responseType: 'blob' });
  return data;
}

export async function downloadContract(saleId, filename) {
  const blob = await getContractBlob(saleId);
  const url = window.URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = filename ?? `contrato-venda-${saleId}.pdf`;
  link.click();
  window.URL.revokeObjectURL(url);
}

export async function previewContract(saleId) {
  const blob = await getContractPreviewBlob(saleId);
  const url = window.URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
  const previewWindow = window.open(url, '_blank');

  if (!previewWindow) {
    window.URL.revokeObjectURL(url);
    const error = new Error('popup_blocked');
    error.code = 'popup_blocked';
    throw error;
  }
}

export async function uploadSignedContract(saleId, file) {
  const formData = new FormData();
  formData.append('file', file);
  const { data } = await api.post(`/sales/${saleId}/signed-contract`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data ?? data;
}

export async function downloadSignedContract(saleId, filename) {
  const { data } = await api.get(`/sales/${saleId}/signed-contract`, { responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([data]));
  const link = document.createElement('a');
  link.href = url;
  link.download = filename ?? `contrato-assinado-venda-${saleId}.pdf`;
  link.click();
  window.URL.revokeObjectURL(url);
}

export async function downloadCarne(saleId) {
  const { data } = await api.get(`/sales/${saleId}/carne`, { responseType: 'blob' });
  const url = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = `promissoria-venda-${saleId}.pdf`;
  link.click();
  window.URL.revokeObjectURL(url);
}

export async function fetchCarnePreviewHtml(saleId) {
  const { data } = await api.get(`/sales/${saleId}/carne/preview`, {
    responseType: 'text',
    headers: { Accept: 'text/html' },
  });

  return data;
}
