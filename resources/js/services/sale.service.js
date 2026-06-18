import api from './api';

function filenameFromContentDisposition(headers) {
  const raw = headers?.['content-disposition'];
  const match = raw ? /filename="?([^";]+)"?/i.exec(raw) : null;
  return match ? match[1] : null;
}

export async function getContractPreviewBlob(saleId) {
  const { data } = await api.get(`/sales/${saleId}/contract/preview`, { responseType: 'blob' });
  return data;
}

export async function downloadContract(saleId, filename) {
  const response = await api.get(`/sales/${saleId}/contract`, { responseType: 'blob' });
  const resolvedFilename = filename
    ?? filenameFromContentDisposition(response.headers)
    ?? `contrato-venda-${saleId}.pdf`;
  const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = resolvedFilename;
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
