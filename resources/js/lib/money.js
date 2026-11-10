export function formatMoney(amount, currency = 'TND') {
    return new Intl.NumberFormat('fr-TN', {
        style: 'currency', currency, currencyDisplay: 'code',
        minimumFractionDigits: currency === 'TND' ? 3 : 2,
        maximumFractionDigits: currency === 'TND' ? 3 : 2,
    }).format(Number(amount ?? 0));
}

export function tunisTime(value) {
    return new Intl.DateTimeFormat('fr-TN', {
        timeZone: 'Africa/Tunis', hourCycle: 'h23', hour: '2-digit', minute: '2-digit', second: '2-digit',
    }).format(new Date(value));
}
