import { jsPDF } from 'jspdf';
import { svg2pdf } from 'svg2pdf.js';
import { buildTechnicalMapSvg } from '@/utils/technicalMapSvg';

function parseSvgElement(svgString) {
  const parser = new DOMParser();
  const document = parser.parseFromString(svgString, 'image/svg+xml');
  const svgElement = document.documentElement;

  if (svgElement.querySelector('parsererror')) {
    throw new Error('Não foi possível gerar o SVG da planta técnica.');
  }

  return svgElement;
}

function paperFormatToJsPdf(paperSize, orientation) {
  const format = String(paperSize || 'A3').toLowerCase();

  return {
    orientation: orientation === 'portrait' ? 'portrait' : 'landscape',
    unit: 'pt',
    format,
  };
}

function triggerDownload(blob, filename) {
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);
}

export function buildTechnicalMapDocument(data, options = {}) {
  return buildTechnicalMapSvg(data, options);
}

export async function downloadTechnicalMapSvg(data, options = {}, filename = null) {
  const result = buildTechnicalMapSvg(data, options);
  const safeName = filename || `planta-tecnica-${slugify(data.development?.name)}.svg`;
  const blob = new Blob([result.svg], { type: 'image/svg+xml;charset=utf-8' });
  triggerDownload(blob, safeName);

  return result;
}

export async function downloadTechnicalMapPdf(data, options = {}, filename = null) {
  const result = buildTechnicalMapSvg(data, options);
  const svgElement = parseSvgElement(result.svg);
  const pdfOptions = paperFormatToJsPdf(result.paperSize, result.orientation);
  const doc = new jsPDF(pdfOptions);

  await svg2pdf(svgElement, doc, {
    x: 0,
    y: 0,
    width: result.width,
    height: result.height,
  });

  const safeName = filename || `planta-tecnica-${slugify(data.development?.name)}.pdf`;
  doc.save(safeName);

  return result;
}

export async function renderTechnicalMapPdfBlob(data, options = {}) {
  const result = buildTechnicalMapSvg(data, options);
  const svgElement = parseSvgElement(result.svg);
  const pdfOptions = paperFormatToJsPdf(result.paperSize, result.orientation);
  const doc = new jsPDF(pdfOptions);

  await svg2pdf(svgElement, doc, {
    x: 0,
    y: 0,
    width: result.width,
    height: result.height,
  });

  return {
    ...result,
    blob: doc.output('blob'),
  };
}

function slugify(value) {
  return String(value ?? 'empreendimento')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'empreendimento';
}
