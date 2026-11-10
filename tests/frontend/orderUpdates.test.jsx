import { act, cleanup, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useOrderUpdates } from '../../resources/js/hooks/useOrderUpdates';
import { fetchAdminDashboard, fetchAdminOrders, fetchOrders, fetchRestaurantSettings } from '../../resources/js/lib/api';

vi.mock('../../resources/js/lib/api', () => ({
    fetchAdminDashboard: vi.fn(), fetchAdminOrders: vi.fn(), fetchOrders: vi.fn(), fetchRestaurantSettings: vi.fn(),
}));

const flush = () => act(async () => { await Promise.resolve(); await Promise.resolve(); });

describe('automatic order refresh', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        fetchRestaurantSettings.mockResolvedValue({ currency: 'TND' });
        fetchAdminDashboard.mockResolvedValue({ pending_orders_count: 1 });
        fetchAdminOrders.mockResolvedValue([{ id: 1, status: 'pending' }]);
        fetchOrders.mockResolvedValue([]);
    });
    afterEach(() => { cleanup(); vi.useRealTimers(); });

    const callbacks = () => ({ user: { id: 1, role: 'admin' }, onOrders: vi.fn(), onAdminOrders: vi.fn(), onDashboard: vi.fn(), onSettings: vi.fn(), onNewOrders: vi.fn() });

    it('refreshes every ten seconds, alerts only for new pending orders, and stops on unmount', async () => {
        const props = callbacks();
        const view = renderHook(() => useOrderUpdates(props));
        await flush();
        expect(props.onAdminOrders).toHaveBeenCalledWith([{ id: 1, status: 'pending' }]);
        expect(props.onNewOrders).not.toHaveBeenCalled();
        fetchAdminOrders.mockResolvedValue([{ id: 1, status: 'pending' }, { id: 2, status: 'pending' }]);
        await act(async () => { await vi.advanceTimersByTimeAsync(10000); });
        expect(props.onNewOrders).toHaveBeenCalledExactlyOnceWith([{ id: 2, status: 'pending' }]);
        await act(async () => { await vi.advanceTimersByTimeAsync(10000); });
        expect(props.onNewOrders).toHaveBeenCalledTimes(1);
        view.unmount();
        await act(async () => { await vi.advanceTimersByTimeAsync(20000); });
        expect(fetchAdminOrders).toHaveBeenCalledTimes(3);
    });

    it('shows connection failure while preserving last successful time and recovers on focus', async () => {
        const props = callbacks();
        const { result } = renderHook(() => useOrderUpdates(props));
        await flush();
        const last = result.current.lastSuccess;
        fetchAdminOrders.mockRejectedValueOnce(new Error('offline'));
        await act(async () => { await vi.advanceTimersByTimeAsync(10000); });
        expect(result.current.error).toBe(true);
        expect(result.current.lastSuccess).toBe(last);
        await act(async () => { window.dispatchEvent(new Event('focus')); await Promise.resolve(); });
        expect(result.current.error).toBe(false);
    });

    it('refreshes customer acceptance status and never calls admin endpoints for a customer', async () => {
        const props = { ...callbacks(), user: { id: 2, role: 'client' } };
        fetchOrders.mockResolvedValue([{ id: 1, status: 'pending' }]);
        renderHook(() => useOrderUpdates(props));
        await flush();
        fetchOrders.mockResolvedValue([{ id: 1, status: 'accepted' }]);
        await act(async () => { await vi.advanceTimersByTimeAsync(10000); });
        expect(props.onOrders).toHaveBeenLastCalledWith([{ id: 1, status: 'accepted' }]);
        expect(fetchAdminOrders).not.toHaveBeenCalled();
    });

    it('does not overlap a slow network request', async () => {
        const props = callbacks();
        let resolve;
        fetchAdminOrders.mockReturnValueOnce(new Promise(done => { resolve = done; }));
        renderHook(() => useOrderUpdates(props));
        await act(async () => { window.dispatchEvent(new Event('focus')); await vi.advanceTimersByTimeAsync(20000); });
        expect(fetchAdminOrders).toHaveBeenCalledTimes(1);
        await act(async () => { resolve([]); await Promise.resolve(); });
    });
});
