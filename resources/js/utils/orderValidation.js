export const getCustomerPricingRuleMessage = (customerType = 'retail', unitOfMeasure = 'kg') => {
  if (unitOfMeasure !== 'kg') {
    return 'Pack-based products are priced per pack and do not use the 10kg threshold.';
  }

  return customerType === 'wholesale'
    ? 'Wholesale customers must order at least 10kg per kilogram-based item.'
    : 'Retail customers must stay below 10kg per kilogram-based item.';
};

export const findCustomerPricingQuantityViolation = (items = [], customerType = 'retail', resolveUnit = () => 'kg') => {
  const violatingIndex = items.findIndex((item) => {
    const quantity = Number(item?.quantity ?? 0);
    const unitOfMeasure = resolveUnit(item);

    if (unitOfMeasure !== 'kg') {
      return false;
    }

    if (customerType === 'wholesale') {
      return quantity < 10;
    }

    return quantity >= 10;
  });

  if (violatingIndex === -1) {
    return null;
  }

  return {
    index: violatingIndex,
    message: `Item ${violatingIndex + 1}: ${getCustomerPricingRuleMessage(customerType)}`,
  };
};

export const getOrderTypeRuleMessage = getCustomerPricingRuleMessage;
export const findOrderTypeQuantityViolation = (items = [], orderType = 'retail') => {
  return findCustomerPricingQuantityViolation(items, orderType);
};

export const getApiErrorMessage = (error, fallbackMessage) => {
  const responseData = error?.response?.data;
  const validationErrors = responseData?.errors;

  if (validationErrors && typeof validationErrors === 'object') {
    const firstError = Object.values(validationErrors)
      .flat()
      .find((message) => typeof message === 'string' && message.trim().length > 0);

    if (firstError) {
      return firstError;
    }
  }

  return responseData?.error || responseData?.message || fallbackMessage;
};