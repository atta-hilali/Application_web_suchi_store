import { useEffect, useRef, useState } from 'react';

const fallbackImage = 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?w=1600&q=90';

function VideoBackground({ url, poster }) {
    const video = useRef(null);
    const [unavailable, setUnavailable] = useState(false);
    const [paused, setPaused] = useState(true);
    const [reducedMotion, setReducedMotion] = useState(() => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false);

    useEffect(() => {
        const query = window.matchMedia?.('(prefers-reduced-motion: reduce)');
        const update = () => setReducedMotion(query?.matches ?? false);
        query?.addEventListener('change', update);
        return () => query?.removeEventListener('change', update);
    }, []);

    if (unavailable || reducedMotion) return null;
    const toggle = async () => {
        if (!video.current) return;
        if (video.current.paused) {
            try { await video.current.play(); } catch { setUnavailable(true); }
        } else video.current.pause();
    };

    return <>
        <video ref={video} className="hero-background-video" src={url} poster={poster} autoPlay muted loop playsInline preload="metadata"
            aria-hidden="true" tabIndex={-1} onError={() => setUnavailable(true)} onPause={() => setPaused(true)} onPlay={() => setPaused(false)} />
        <button className="hero-video-toggle" type="button" onClick={toggle} aria-label={paused ? 'Lire la vidéo de fond' : 'Mettre en pause la vidéo de fond'}>
            {paused ? '▶' : 'Ⅱ'}
        </button>
    </>;
}

export default function Hero({ onExploreMenu, media }) {
    const imageUrl = media?.image_url || fallbackImage;
    return (
        <section className="hero" style={{ backgroundImage: `url("${imageUrl}")` }}>
            {media?.type === 'video' && media.video_url && <VideoBackground key={media.video_url} url={media.video_url} poster={imageUrl} />}
            <div className="hero-content">
                <div className="hero-subtitle">Cuisine Japonaise Raffinée</div>
                <h1>Miya Sushi</h1>
                <p>L'art du sushi dans sa forme la plus pure</p>
                <button type="button" className="btn btn-primary" onClick={onExploreMenu}>
                    Explorer la Carte
                </button>
            </div>
        </section>
    );
}
