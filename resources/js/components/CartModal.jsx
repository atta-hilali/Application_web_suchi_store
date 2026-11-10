import { useEffect, useState } from 'react';
import { extractErrorMessage, quoteOrder } from '../lib/api';
import { formatMoney } from '../lib/money';

export default function CartModal({
    cart, deliveryType, deliveryAddress, deliveryPostcode, contactPhone, comments, submitting,
    settings, onClose, onUpdateQuantity, onRemoveItem, onDeliveryTypeChange,
    onDeliveryAddressChange, onDeliveryPostcodeChange, onContactPhoneChange, onCommentsChange, onPlaceOrder,
}) {
    const [pricing, setPricing] = useState(null);
    const [error, setError] = useState('');
    const [revision, setRevision] = useState(0);
    const requestKey = JSON.stringify({ delivery_type: deliveryType,
        delivery_postcode: deliveryType === 'delivery' ? deliveryPostcode : null,
        items: cart.map(item => ({ product_id: item.id, quantity: item.quantity })),
    });
    const quote = pricing?.key === requestKey ? pricing.quote : null;

    useEffect(() => {
        let active = true;
        let refreshTimer;
        setPricing(null);
        setError('');
        if (!cart.length) return undefined;
        const timer = setTimeout(async () => {
            try {
                const next = await quoteOrder(JSON.parse(requestKey));
                if (active) {
                    setPricing({ key: requestKey, quote: next });
                    refreshTimer = setTimeout(() => setRevision(value => value + 1), 30000);
                }
            } catch (failure) {
                if (active) {
                    setError(extractErrorMessage(failure, 'Impossible de vérifier le total. Nouvelle tentative automatique…'));
                    refreshTimer = setTimeout(() => setRevision(value => value + 1), 10000);
                }
            }
        }, 300);
        return () => { active = false; clearTimeout(timer); clearTimeout(refreshTimer); };
    }, [requestKey, revision]);

    const deliveryAvailable = settings?.delivery_enabled && settings.delivery_postcodes.length > 0;
    const submit = async event => {
        event.preventDefault();
        if (!quote || submitting) return;
        await onPlaceOrder(quote);
        setRevision(value => value + 1);
    };

    return <div className="modal" onClick={() => { if (!submitting) onClose(); }}>
        <div className="modal-content" role="dialog" aria-modal="true" aria-labelledby="cart-title" onClick={event => event.stopPropagation()}>
            <div className="modal-header">
                <h2 className="modal-title" id="cart-title">Mon Panier</h2>
                <button type="button" className="close-modal" aria-label="Fermer le panier" disabled={submitting} onClick={onClose}>×</button>
            </div>
            {!cart.length ? <div className="modal-body empty-state"><p>Votre panier est vide</p></div> : <form onSubmit={submit}>
                <fieldset className="checkout-fields" disabled={submitting}>
                    <div className="modal-body">
                        {cart.map(item => {
                            const quotedItem = quote?.items?.find(line => line.product_id === item.id);
                            return <div key={item.id} className="cart-item">
                            <div className="cart-item-info"><div className="cart-item-name">{quotedItem?.product_name ?? item.name}</div><div className="cart-item-price">{formatMoney(quotedItem?.product_price ?? item.price)} l'unité</div></div>
                            <div className="cart-item-actions"><div className="quantity-control">
                                <button type="button" className="quantity-btn" aria-label={`Réduire ${item.name}`} onClick={() => onUpdateQuantity(item.id, -1)}>−</button>
                                <span className="quantity-value">{item.quantity}</span>
                                <button type="button" className="quantity-btn" aria-label={`Augmenter ${item.name}`} disabled={item.quantity >= (settings?.max_quantity ?? 99)} onClick={() => onUpdateQuantity(item.id, 1)}>+</button>
                            </div><button type="button" className="remove-btn" onClick={() => onRemoveItem(item.id)}>Retirer</button></div>
                        </div>; })}
                        <fieldset className="delivery-options"><legend>Mode de réception</legend><div className="radio-group">
                            <label className="radio-label"><input type="radio" name="delivery_type" value="takeaway" checked={deliveryType === 'takeaway'} onChange={e => onDeliveryTypeChange(e.target.value)} />À emporter</label>
                            <label className="radio-label"><input type="radio" name="delivery_type" value="delivery" disabled={!deliveryAvailable} checked={deliveryType === 'delivery'} onChange={e => onDeliveryTypeChange(e.target.value)} />Livraison</label>
                        </div>{!deliveryAvailable && <p className="form-hint">Livraison indisponible actuellement.</p>}</fieldset>
                        {deliveryType === 'delivery' && <>
                            <label className="form-group">Adresse de livraison<input className="form-input" value={deliveryAddress} onChange={e => onDeliveryAddressChange(e.target.value)} maxLength={1000} required placeholder="Numéro, rue, ville" /></label>
                            <label className="form-group">Code postal<input className="form-input" value={deliveryPostcode} onChange={e => onDeliveryPostcodeChange(e.target.value)} inputMode="numeric" pattern="[0-9]{4}" maxLength={4} required /></label>
                            <p className="form-hint">Codes desservis : {settings?.delivery_postcodes.join(', ')}</p>
                        </>}
                        <label className="form-group">Téléphone de contact<input className="form-input" type="tel" value={contactPhone} onChange={e => onContactPhoneChange(e.target.value)} minLength={8} maxLength={30} required placeholder="+216 …" /></label>
                        <label className="form-group">Commentaires et instructions<textarea className="form-input" value={comments} onChange={e => onCommentsChange(e.target.value)} maxLength={1500} placeholder="Allergies, préférences, instructions de livraison…" /></label>
                    </div>
                    <div className="cart-total" aria-live="polite">
                        {quote ? <>
                            <div className="total-row"><span>Sous-total</span><span>{formatMoney(quote.subtotal)}</span></div>
                            <div className="total-row"><span>Frais de livraison</span><span>{formatMoney(quote.delivery_fee)}</span></div>
                            <div className="total-row"><span className="total-label">Total à payer</span><strong className="total-amount">{formatMoney(quote.total)}</strong></div>
                        </> : error ? <p role="alert">{error}</p> : <p role="status">Vérification du total…</p>}
                        <button type="submit" className="btn btn-primary full-width" disabled={!quote || submitting}>{submitting ? 'Validation…' : 'Valider la commande'}</button>
                    </div>
                </fieldset>
            </form>}
        </div>
    </div>;
}
