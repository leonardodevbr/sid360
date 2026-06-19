import api from './api';

function filenameFromContentDisposition(headers) {
  const raw = headers?.['content-disposition'];
  const match = raw ? /filename="?([^";]+)"?/i.exec(raw) : null;
  return match ? match[1] : null;
}

function triggerBlobDownload(blob, filename) {
  const url = window.URL.createObjectURL(new Blob([blob]));
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);
}

// --- Documentos do cliente (perfil geral versionado) ---

export async function listClientDocuments(clientId) {
  const { data } = await api.get(`/clients/${clientId}/documents`);
  return data.data ?? data;
}

export async function uploadClientDocument(clientId, file, type, side) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', type);
  if (side) {
    formData.append('side', side);
  }
  const { data } = await api.post(`/clients/${clientId}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data ?? data;
}

export async function downloadClientDocument(clientId, documentId, filename) {
  const response = await api.get(`/clients/${clientId}/documents/${documentId}/download`, {
    responseType: 'blob',
  });
  triggerBlobDownload(response.data, filename ?? filenameFromContentDisposition(response.headers) ?? 'documento');
}

// Busca o documento como blob autenticado e devolve uma object URL para
// visualização inline (img/iframe), sem nunca expor uma URL pública do R2.
// Quem chamar é responsável por window.URL.revokeObjectURL(url) ao finalizar.
export async function previewClientDocument(clientId, documentId, mimeType) {
  const response = await api.get(`/clients/${clientId}/documents/${documentId}/preview`, {
    responseType: 'blob',
  });
  const blob = new Blob([response.data], { type: mimeType ?? response.data.type });
  return window.URL.createObjectURL(blob);
}

export async function deleteClientDocument(clientId, documentId) {
  const { data } = await api.post(`/clients/${clientId}/documents/${documentId}/delete`);
  return data;
}

// --- Documentos da venda (cópia congelada por empreendimento) ---

export async function listSaleDocuments(saleId) {
  const { data } = await api.get(`/sales/${saleId}/documents`);
  return data.data ?? data;
}

export async function uploadSaleDocument(saleId, file, type, side) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', type);
  if (side) {
    formData.append('side', side);
  }
  const { data } = await api.post(`/sales/${saleId}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data ?? data;
}

export async function downloadSaleDocument(saleId, documentId, filename) {
  const response = await api.get(`/sales/${saleId}/documents/${documentId}/download`, {
    responseType: 'blob',
  });
  triggerBlobDownload(response.data, filename ?? filenameFromContentDisposition(response.headers) ?? 'documento');
}

// Mesma lógica de previewClientDocument, mas para documentos congelados da venda.
export async function previewSaleDocument(saleId, documentId, mimeType) {
  const response = await api.get(`/sales/${saleId}/documents/${documentId}/preview`, {
    responseType: 'blob',
  });
  const blob = new Blob([response.data], { type: mimeType ?? response.data.type });
  return window.URL.createObjectURL(blob);
}
