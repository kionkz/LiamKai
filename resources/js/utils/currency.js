const moneyFormatter = new Intl.NumberFormat('en-PH', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

export const formatMoneyNumber = (value) => moneyFormatter.format(Number(value || 0));

export const formatPeso = (value) => `₱${formatMoneyNumber(value)}`;

export const formatPhp = (value) => `PHP ${formatMoneyNumber(value)}`;
