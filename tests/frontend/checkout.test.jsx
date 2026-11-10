import React from 'react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CartModal from '../../resources/js/components/CartModal';
import RestaurantSettings from '../../resources/js/components/RestaurantSettings';
import { quoteOrder, saveRestaurantSettings } from '../../resources/js/lib/api';
import { formatMoney, tunisTime } from '../../resources/js/lib/money';

vi.mock('../../resources/js/lib/api', () => ({
    quoteOrder: vi.fn(), saveRestaurantSettings: vi.fn(), extractErrorMessage: error => error.message,
}));

const settings = { currency: 'TND', delivery_enabled: true, delivery_postcodes: ['3062'], opening_days: [1, 2, 3, 4, 5, 6, 7],
    closed_dates: [], opens_at: '11:30', closes_at: '22:30', is_open: true, ordering_enabled: true,
    minimum_order_amount: '0.000', delivery_fee: '2.125', max_quantity: 99 };
const quoted = { subtotal: '3.015', delivery_fee: '2.125', total: '5.140', quote_token: 'signed-total' };
const baseProps = () => ({ cart: [{ id: 1, name: 'Sushi', price: 1.005, quantity: 3 }], settings,
    deliveryType: 'delivery', deliveryPostcode: '3062', deliveryAddress: 'Sfax', contactPhone: '+21628820982', comments: '', submitting: false,
    onClose: vi.fn(), onUpdateQuantity: vi.fn(), onRemoveItem: vi.fn(), onDeliveryTypeChange: vi.fn(),
    onDeliveryAddressChange: vi.fn(), onDeliveryPostcodeChange: vi.fn(), onContactPhoneChange: vi.fn(), onCommentsChange: vi.fn(), onPlaceOrder: vi.fn(),
});

describe('checkout confirmation', () => {
    beforeEach(() => { vi.useFakeTimers(); quoteOrder.mockResolvedValue(quoted); });
    afterEach(() => { cleanup(); vi.useRealTimers(); });
    const settleQuote = () => act(async () => { await vi.advanceTimersByTimeAsync(300); });

    it('shows the server subtotal, delivery fee and millime-accurate final amount before confirmation', async () => {
        const props = baseProps();
        render(<CartModal {...props} />);
        expect(screen.getByRole('button', { name: 'Valider la commande' }).disabled).toBe(true);
        await settleQuote();
        expect(screen.getByText(/5,140\s*TND/)).toBeTruthy();
        expect(screen.getByText(/2,125\s*TND/)).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Valider la commande' }));
        await act(async () => {});
        expect(props.onPlaceOrder).toHaveBeenCalledWith(quoted);
    });

    it('disables confirmation immediately when the basket changes and ignores an obsolete quote response', async () => {
        let resolveOld;
        quoteOrder.mockReturnValueOnce(new Promise(resolve => { resolveOld = resolve; }));
        const props = baseProps();
        const view = render(<CartModal {...props} />);
        await settleQuote();
        view.rerender(<CartModal {...props} cart={[{ ...props.cart[0], quantity: 4 }]} />);
        await act(async () => { resolveOld(quoted); await Promise.resolve(); });
        expect(screen.getByRole('button', { name: 'Valider la commande' }).disabled).toBe(true);
        await settleQuote();
        expect(quoteOrder).toHaveBeenLastCalledWith(expect.objectContaining({ items: [{ product_id: 1, quantity: 4 }] }));
    });

    it('blocks confirmation when the server rejects an area, minimum or opening rule', async () => {
        quoteOrder.mockRejectedValueOnce(new Error('Code postal hors zone.'));
        render(<CartModal {...baseProps()} />);
        await settleQuote();
        expect(screen.getByRole('alert').textContent).toBe('Code postal hors zone.');
        expect(screen.getByRole('button', { name: 'Valider la commande' }).disabled).toBe(true);
    });

    it('disables delivery when the operator has not approved a delivery area', () => {
        render(<CartModal {...baseProps()} settings={{ ...settings, delivery_enabled: false, delivery_postcodes: [] }} deliveryType="takeaway" />);
        expect(screen.getByRole('radio', { name: 'Livraison' }).disabled).toBe(true);
    });

    it('shows refreshed unit prices from the server instead of an old menu snapshot', async () => {
        quoteOrder.mockResolvedValueOnce({ ...quoted, items: [{ product_id: 1, product_name: 'Sushi', product_price: '1.555' }] });
        render(<CartModal {...baseProps()} />);
        await settleQuote();
        expect(screen.getByText(/1,555\s*TND l'unité/)).toBeTruthy();
        expect(screen.queryByText(/1,005\s*TND l'unité/)).toBeNull();
    });

    it('saves configurable hours, postcodes and three-decimal fees through the admin form', async () => {
        const saved = vi.fn();
        saveRestaurantSettings.mockResolvedValue(settings);
        render(<RestaurantSettings settings={settings} onSaved={saved} />);
        fireEvent.change(screen.getByLabelText('Frais de livraison (TND)'), { target: { value: '4.125' } });
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer les paramètres' }));
        await act(async () => {});
        expect(saveRestaurantSettings).toHaveBeenCalledWith(expect.objectContaining({ delivery_fee: '4.125', delivery_postcodes: ['3062'], opens_at: '11:30' }));
        expect(saved).toHaveBeenCalledWith(settings);
    });
});

it('formats TND with three decimals and explicitly uses Tunis time even during European summer time', () => {
    expect(formatMoney('1.005')).toContain('1,005');
    expect(formatMoney('1.005')).toContain('TND');
    expect(tunisTime('2026-07-01T12:00:00Z')).toBe('13:00:00');
});
