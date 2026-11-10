import { useEffect, useState } from 'react';
import { extractErrorMessage, saveHeroMedia } from '../lib/api';

export default function HeroMediaSettings({ media, onSaved }) {
    const [mediaType, setMediaType] = useState(media.type);
    const [source, setSource] = useState('existing');
    const [file, setFile] = useState(null);
    const [url, setUrl] = useState('');
    const [localPreview, setLocalPreview] = useState('');
    const [saving, setSaving] = useState(false);
    const [progress, setProgress] = useState(0);
    const [message, setMessage] = useState(null);
    const [previewError, setPreviewError] = useState(false);
    const video = mediaType === 'video';
    const previewUrl = source === 'upload' ? localPreview : source === 'url' ? (url.startsWith('https://') ? url : '') : media[`${mediaType}_url`];

    useEffect(() => {
        if (!file) { setLocalPreview(''); return undefined; }
        const objectUrl = URL.createObjectURL(file);
        setLocalPreview(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);
    useEffect(() => { setPreviewError(false); }, [previewUrl, mediaType]);

    const chooseFile = event => {
        const next = event.target.files?.[0] ?? null;
        setMessage(null);
        if (next && next.size > (video ? 30 : 8) * 1024 * 1024) {
            setMessage({ error: true, text: `Fichier trop volumineux. Maximum : ${video ? 30 : 8} Mo.` });
            setFile(null);
            event.target.value = '';
            return;
        }
        setFile(next);
    };
    const submit = async event => {
        event.preventDefault();
        setSaving(true);
        setMessage(null);
        setProgress(0);
        try {
            const saved = await saveHeroMedia({ mediaType, source, file, url }, setProgress);
            onSaved(saved);
            setFile(null);
            setUrl('');
            setSource('existing');
            setMessage({ error: false, text: 'Le fond d’accueil a été publié.' });
        } catch (error) {
            setMessage({ error: true, text: error?.response?.status === 413 ? 'Fichier trop volumineux pour cet hébergement.' : extractErrorMessage(error, 'Publication impossible. Votre fond actuel est conservé.') });
        } finally { setSaving(false); }
    };

    return <section className="manager-panel hero-media-settings">
        <h3 className="manager-title">Fond de la page d’accueil</h3>
        <p>Choisissez une image ou une vidéo. La vidéo sera lue en boucle, sans son, derrière le titre.</p>
        <form onSubmit={submit}>
            <fieldset className="hero-media-fields" disabled={saving}>
                <fieldset><legend>Type de fond</legend><div className="radio-group">
                    {['image', 'video'].map(type => <label className="radio-label" key={type}>
                        <input type="radio" name="hero_type" value={type} checked={mediaType === type} onChange={() => { setMediaType(type); setFile(null); setUrl(''); setSource('existing'); setMessage(null); }} />{type === 'image' ? 'Image' : 'Vidéo'}
                    </label>)}
                </div></fieldset>
                <label className="form-group">Source<select className="form-input" value={source} onChange={event => { setSource(event.target.value); setFile(null); setMessage(null); }}>
                    <option value="existing">Réutiliser {video ? 'la vidéo enregistrée' : 'l’image enregistrée'}</option>
                    <option value="upload">Importer un fichier</option>
                    <option value="url">Utiliser un lien direct</option>
                </select></label>
                {source === 'upload' && <label className="form-group">{video ? 'Fichier vidéo' : 'Fichier image'}<input key={mediaType} className="form-input" type="file" required accept={video ? 'video/mp4,video/webm,.mp4,.webm' : 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'} onChange={chooseFile} /></label>}
                {source === 'url' && <label className="form-group">Lien direct HTTPS<input className="form-input" type="url" required value={url} onChange={event => setUrl(event.target.value)} placeholder={video ? 'https://exemple.com/video.mp4' : 'https://exemple.com/image.jpg'} /></label>}
                <p className="form-hint">{video ? 'MP4 (H.264 recommandé) ou WebM, 30 Mo maximum. Privilégiez une vidéo courte au format paysage. Les liens YouTube ne sont pas acceptés.' : 'JPG, PNG ou WebP, 8 Mo maximum. Format paysage recommandé.'}</p>
                <div className="hero-media-preview">
                    {previewUrl ? video ? <video key={previewUrl} src={previewUrl} poster={media.image_url} controls muted playsInline preload="metadata" onError={() => setPreviewError(true)} />
                        : <img src={previewUrl} alt="Aperçu du fond d’accueil" onError={() => setPreviewError(true)} /> : <p>Choisissez un fichier ou un lien pour voir l’aperçu.</p>}
                </div>
                {previewError && <p role="alert">Impossible d’afficher l’aperçu. Vérifiez le fichier ou le lien.</p>}
                {saving && <p role="status">{source === 'upload' ? `Envoi : ${progress} %` : 'Publication…'}</p>}
                {message && <p role={message.error ? 'alert' : 'status'}>{message.text}</p>}
                <button className="btn btn-primary" type="submit" disabled={saving || (source === 'upload' && !file) || !previewUrl || previewError}>{saving ? 'Publication…' : 'Publier ce fond'}</button>
            </fieldset>
        </form>
    </section>;
}
