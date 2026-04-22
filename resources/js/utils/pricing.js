export const normalizeDiscountPercent = (value) => {
  const numeric = Number(value ?? 0);

  if (!Number.isFinite(numeric)) return 0;
  if (numeric < 0) return 0;
  if (numeric > 100) return 100;
  return Math.round(numeric * 100) / 100;
};

export const calculateDiscountedPrice = (retailPrice, discountPercent) => {
  const retail = Number(retailPrice ?? 0);
  const discount = normalizeDiscountPercent(discountPercent);

  if (!Number.isFinite(retail) || retail <= 0) return 0;
  return Math.round(retail * (1 - (discount / 100)) * 100) / 100;
};

export const resolveActivePricing = (product) => {
  if (!product) return null;
  const pricing = Array.isArray(product.pricing) ? product.pricing[0] : null;
  return pricing || null;
};

export const resolveRetailPrice = (product) => {
  const pricing = resolveActivePricing(product);
  const retail = Number(pricing?.retail_price ?? product?.retail_price ?? product?.base_price ?? 0);
  return Number.isFinite(retail) ? retail : 0;
};

export const resolveDiscountPercent = (product) => {
  const pricing = resolveActivePricing(product);
  return normalizeDiscountPercent(pricing?.discount_percent ?? product?.discount_percent ?? 0);
};

export const resolveDiscountedPrice = (product) => {
  const pricing = resolveActivePricing(product);
  const explicitPrice = Number(pricing?.discounted_price ?? product?.discounted_price);

  if (Number.isFinite(explicitPrice) && explicitPrice > 0) {
    return explicitPrice;
  }

  return calculateDiscountedPrice(resolveRetailPrice(product), resolveDiscountPercent(product));
};

export const resolveOrderUnitPrice = (product, customerType = 'retail') => {
  return customerType === 'wholesale'
    ? resolveDiscountedPrice(product)
    : resolveRetailPrice(product);
};

export const resolveCustomerPriceLabel = (customerType = 'retail') => {
  return customerType === 'wholesale' ? 'Wholesale' : 'Retail';
};

/**
 * Returns the fixed discount amount for a product (retail_price - discounted_price).
 */
export const resolveDiscountAmount = (product) => {
  const retail = resolveRetailPrice(product);
  const discounted = resolveDiscountedPrice(product);
  return Math.max(0, Math.round((retail - discounted) * 100) / 100);
};

/**
 * Converts a fixed discount amount to a discount percent for backend storage.
 */
export const discountAmountToPercent = (retailPrice, discountAmount) => {
  const retail = Number(retailPrice || 0);
  const amount = Number(discountAmount || 0);
  if (!retail || retail <= 0) return 0;
  return Math.round((amount / retail) * 100 * 100) / 100;
};

/**
 * Calculates the final (wholesale) price from a retail price and a fixed discount amount.
 */
export const calculateDiscountedPriceFromAmount = (retailPrice, discountAmount) => {
  const retail = Number(retailPrice || 0);
  const amount = Number(discountAmount || 0);
  if (!Number.isFinite(retail) || retail <= 0) return 0;
  return Math.max(0, Math.round((retail - amount) * 100) / 100);
};