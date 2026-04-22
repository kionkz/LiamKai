import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

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
        0: { fontStyle: 'bold', cellWidth: 120 },
        1: { cellWidth: 340 },
      },
      styles: { fontSize: 10, cellPadding: 2, textColor: [31, 41, 55] },
    });
    currentY = doc.lastAutoTable.finalY + 16;
  }

  autoTable(doc, {
    startY: currentY,
    head: [['Item', 'Qty', 'Unit Price', 'Amount']],
    body: items.map((item) => [item.name, item.qty, item.unitPrice, item.amount]),
    headStyles: { fillColor: [229, 124, 42] },
    styles: { fontSize: 10 },
  });

  currentY = doc.lastAutoTable.finalY + 16;

  if (totals.length > 0) {
    autoTable(doc, {
      startY: currentY,
      theme: 'plain',
      body: totals.map((entry) => [entry.label, entry.value]),
      columnStyles: {
        0: { cellWidth: 120, halign: 'left', fontStyle: 'bold' },
        1: { cellWidth: 120, halign: 'right' },
      },
      margin: { left: 355 },
      styles: { fontSize: 10, cellPadding: 2, textColor: [31, 41, 55] },
    });
  }

  const pdfUrl = doc.output('bloburl');
  window.open(pdfUrl, '_blank', 'noopener');
  doc.save(filename);
};