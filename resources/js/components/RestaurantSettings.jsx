import { useState } from 'react';
import { extractErrorMessage, saveRestaurantSettings } from '../lib/api';

const days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

export default function RestaurantSettings({ settings, onSaved }) {
    const [form, setForm] = useState(() => ({ ...settings,
        postcodes: settings.delivery_postcodes.join(', '), holidays: settings.closed_dates.join(', '),
    }));
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);
    const change = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const submit = async event => {
        event.preventDefault();
        setSaving(true);
        setMessage(null);
        try {
            const saved = await saveRestaurantSettings({
                delivery_fee: form.delivery_fee, minimum_order_amount: form.minimum_order_amount,
                ordering_enabled: form.ordering_enabled, delivery_enabled: form.delivery_enabled,
                delivery_postcodes: form.postcodes.split(/[\s,;]+/).filter(Boolean),
                opening_days: form.opening_days, opens_at: form.opens_at, closes_at: form.closes_at,
                closed_dates: form.holidays.split(/[\s,;]+/).filter(Boolean),
            });
            onSaved(saved);
            setMessage({ text: 'Paramètres enregistrés.', error: false });
        } catch (error) {
            setMessage({ text: extractErrorMessage(error, 'Enregistrement impossible.'), error: true });
        } finally { setSaving(false); }
    };
    return <section className="manager-panel restaurant-settings">
        <h3 className="manager-title">Commandes, horaires et livraison</h3>
        <p>Devise : TND (3 décimales). Tous les horaires sont en heure de Tunis.</p>
        <form onSubmit={submit}>
            <label className="checkbox-row"><input type="checkbox" checked={form.ordering_enabled} onChange={e => change('ordering_enabled', e.target.checked)} /> Accepter les commandes</label>
            <fieldset><legend>Jours d'ouverture</legend><div className="settings-days">{days.map((day, index) => <label key={day} className="checkbox-row">
                <input type="checkbox" checked={form.opening_days.includes(index + 1)} onChange={e => change('opening_days', e.target.checked ? [...form.opening_days, index + 1] : form.opening_days.filter(d => d !== index + 1))} />{day}
            </label>)}</div></fieldset>
            <div className="form-grid">
                <label className="form-group">Ouverture<input className="form-input" type="time" required value={form.opens_at} onChange={e => change('opens_at', e.target.value)} /></label>
                <label className="form-group">Fermeture<input className="form-input" type="time" required value={form.closes_at} onChange={e => change('closes_at', e.target.value)} /></label>
            </div>
            <label className="form-group">Dates de fermeture (AAAA-MM-JJ, séparées par des virgules)<input className="form-input" value={form.holidays} onChange={e => change('holidays', e.target.value)} placeholder="2026-12-31, 2027-01-01" /></label>
            <label className="checkbox-row"><input type="checkbox" checked={form.delivery_enabled} onChange={e => change('delivery_enabled', e.target.checked)} /> Activer la livraison</label>
            <label className="form-group">Codes postaux desservis (4 chiffres, séparés par des virgules)<input className="form-input" value={form.postcodes} onChange={e => change('postcodes', e.target.value)} placeholder="Ex. 3062" required={form.delivery_enabled} /></label>
            <p className="form-hint">La livraison exige au moins un code postal. Le retrait sur place reste disponible pendant les horaires d'ouverture.</p>
            <div className="form-grid">
                <label className="form-group">Frais de livraison (TND)<input className="form-input" type="number" step="0.001" min="0" max="99999.999" required value={form.delivery_fee} onChange={e => change('delivery_fee', e.target.value)} /></label>
                <label className="form-group">Minimum de commande (TND)<input className="form-input" type="number" step="0.001" min="0" max="999999.999" required value={form.minimum_order_amount} onChange={e => change('minimum_order_amount', e.target.value)} /></label>
            </div>
            {message && <p role={message.error ? 'alert' : 'status'}>{message.text}</p>}
            <button type="submit" className="btn btn-primary" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer les paramètres'}</button>
        </form>
    </section>;
}
