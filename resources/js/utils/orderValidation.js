export const getOrderTypeRuleMessage = (orderType) => {
  return orderType === 'wholesale'
    ? 'Wholesale orders must be 10kg or more per item.'
    : 'Retail orders must be below 10kg per item.';
};

export const findOrderTypeQuantityViolation = (items = [], orderType = 'retail') => {
  const violatingIndex = items.findIndex((item) => {
    const quantity = Number(item?.quantity ?? 0);

    if (orderType === 'wholesale') {
      return quantity < 10;
    }

    return quantity >= 10;
  });

  if (violatingIndex === -1) {
    return null;
  }

  return {
    index: violatingIndex,
    message: `Item ${violatingIndex + 1}: ${getOrderTypeRuleMessage(orderType)}`,
  };
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