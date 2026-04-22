import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

const amountFormatter = new Intl.NumberFormat('en-PH', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const currencySymbols = new Set(['₱', 'PHP', 'P']);

const formatAmountCell = (value) => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return `PHP ${amountFormatter.format(value)}`;
  }

  const text = String(value ?? '').trim();
  const match = text.match(/^([^\d-]*)(-?\d[\d,]*(?:\.\d+)?)(.*)$/);

  if (!match) {
    return text;
  }

  const [, prefix, rawNumber, suffix] = match;
  const parsed = Number(rawNumber.replace(/,/g, ''));

  if (!Number.isFinite(parsed)) {
    return text;
  }

  const trimmedPrefix = prefix.trim();
  const normalizedPrefix = currencySymbols.has(trimmedPrefix) ? 'PHP ' : prefix;

  return `${normalizedPrefix}${amountFormatter.format(parsed)}${suffix}`;
};

export const exportReceiptPdf = ({
  title = 'Receipt',
  subtitle = '',
  filename = 'receipt.pdf',
  meta = [],
  items = [],
  totals = [],
}) => {
  const doc = new jsPDF({ unit: 'pt', format: 'a4' });

  doc.setFontSize(18);
  doc.setTextColor(10, 29, 55);
  doc.text(title, 40, 52);

  if (subtitle) {
    doc.setFontSize(10);
    doc.setTextColor(100, 116, 139);
    doc.text(subtitle, 40, 70);
  }

  let currentY = 96;

  if (meta.length > 0) {
    autoTable(doc, {
      startY: currentY,
      theme: 'plain',
      body: meta.map((entry) => [entry.label, entry.value]),
      columnStyles: {
        0: { fontStyle: 'bold', cellWidth: 110 },
        1: { cellWidth: 395 },
      },
      styles: { fontSize: 10.5, cellPadding: 3, textColor: [31, 41, 55] },
    });
    currentY = doc.lastAutoTable.finalY + 16;
  }

  autoTable(doc, {
    startY: currentY,
    head: [['Item', 'Qty', 'Unit Price', 'Amount']],
    body: items.map((item) => [
      item.name,
      item.qty,
      formatAmountCell(item.unitPrice),
      formatAmountCell(item.amount),
    ]),
    headStyles: { fillColor: [229, 124, 42], textColor: [255, 255, 255], fontStyle: 'bold' },
    styles: {
      fontSize: 10.5,
      cellPadding: { top: 8, right: 10, bottom: 8, left: 10 },
      textColor: [31, 41, 55],
      lineColor: [234, 237, 242],
      lineWidth: 0.4,
    },
    columnStyles: {
      0: { cellWidth: 220, halign: 'left' },
      1: { cellWidth: 75, halign: 'center' },
      2: { cellWidth: 105, halign: 'right' },
      3: { cellWidth: 105, halign: 'right' },
    },
  });

  currentY = doc.lastAutoTable.finalY + 16;

  if (totals.length > 0) {
    autoTable(doc, {
      startY: currentY,
      theme: 'plain',
      body: totals.map((entry) => [entry.label, formatAmountCell(entry.value)]),
      columnStyles: {
        0: { cellWidth: 110, halign: 'left', fontStyle: 'bold' },
        1: { cellWidth: 90, halign: 'right' },
      },
      margin: { left: 315 },
      styles: { fontSize: 10, cellPadding: 2, textColor: [31, 41, 55] },
      didParseCell: (hookData) => {
        const row = totals[hookData.row.index];
        if (row && String(row.label).toLowerCase() === 'total') {
          hookData.cell.styles.fontStyle = 'bold';
          hookData.cell.styles.fontSize = 11;
        }
      },
    });
  }

  const pdfUrl = doc.output('bloburl');
  window.open(pdfUrl, '_blank', 'noopener');
  doc.save(filename);
};
