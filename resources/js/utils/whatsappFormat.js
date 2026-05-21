/**
 * Converte formatação estilo WhatsApp para HTML seguro.
 * *bold* · _italic_ · ~strike~ · ```mono```
 */
export function formatWhatsappHtml(text) {
  if (!text) return '';

  let html = String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  html = html.replace(/```([^`]+)```/g, '<code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[11px]">$1</code>');
  html = html.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
  html = html.replace(/_([^_\n]+)_/g, '<em class="text-slate-600">$1</em>');
  html = html.replace(/~([^~\n]+)~/g, '<del>$1</del>');
  html = html.replace(
    /(https?:\/\/[^\s<]+)/g,
    '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-action underline break-all">$1</a>',
  );

  return html;
}
