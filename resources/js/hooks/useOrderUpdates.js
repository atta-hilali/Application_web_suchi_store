import { useEffect, useRef, useState } from 'react';
import { fetchAdminDashboard, fetchAdminOrders, fetchOrders, fetchRestaurantSettings } from '../lib/api';

export function useOrderUpdates({ user, onOrders, onAdminOrders, onDashboard, onSettings, onNewOrders }) {
    const callbacks = useRef({});
    callbacks.current = { onOrders, onAdminOrders, onDashboard, onSettings, onNewOrders };
    const refreshRef = useRef(() => {});
    const [sync, setSync] = useState({ lastSuccess: null, error: false });

    useEffect(() => {
        if (!user) { setSync({ lastSuccess: null, error: false }); return undefined; }
        let disposed = false;
        let inFlight = false;
        let timer;
        let knownIds = null;
        const poll = async () => {
            if (disposed || inFlight) return;
            clearTimeout(timer);
            inFlight = true;
            try {
                const admin = user.role === 'admin';
                const [orders, settings, dashboard] = await Promise.all([
                    admin ? fetchAdminOrders() : fetchOrders(), fetchRestaurantSettings(),
                    admin ? fetchAdminDashboard() : Promise.resolve(null),
                ]);
                if (disposed) return;
                if (admin) {
                    const newPending = knownIds === null ? [] : orders.filter(order => !knownIds.has(order.id) && order.status === 'pending');
                    knownIds = new Set(orders.map(order => order.id));
                    callbacks.current.onAdminOrders(orders);
                    callbacks.current.onDashboard(dashboard);
                    if (newPending.length) callbacks.current.onNewOrders(newPending);
                } else {
                    callbacks.current.onOrders(orders);
                }
                callbacks.current.onSettings(settings);
                setSync({ lastSuccess: Date.now(), error: false });
            } catch {
                if (!disposed) setSync(current => ({ ...current, error: true }));
            } finally {
                inFlight = false;
                if (!disposed) timer = setTimeout(poll, 10000);
            }
        };
        const onVisible = () => { if (document.visibilityState === 'visible') poll(); };
        refreshRef.current = poll;
        poll();
        window.addEventListener('focus', poll);
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            disposed = true;
            clearTimeout(timer);
            refreshRef.current = () => {};
            window.removeEventListener('focus', poll);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [user?.id, user?.role]);

    return { ...sync, refresh: () => refreshRef.current() };
}
