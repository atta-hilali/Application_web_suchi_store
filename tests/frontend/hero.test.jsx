import React from 'react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Hero from '../../resources/js/components/Hero';
import HeroMediaSettings from '../../resources/js/components/HeroMediaSettings';
import { saveHeroMedia } from '../../resources/js/lib/api';

vi.mock('../../resources/js/lib/api', () => ({ saveHeroMedia: vi.fn(), extractErrorMessage: error => error.message }));
const media = { type: 'video', video_url: '/videos/sushi-intro.mp4', image_url: '/images/poster.jpg' };

beforeEach(() => {
    window.matchMedia = vi.fn(() => ({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() }));
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

it('renders a silent looping inline video behind the existing hero text', () => {
    const { container } = render(<Hero media={media} onExploreMenu={vi.fn()} />);
    const video = container.querySelector('video');
    expect(video.getAttribute('src')).toBe('/videos/sushi-intro.mp4');
    expect(video.muted).toBe(true);
    expect(video.autoplay).toBe(true);
    expect(video.loop).toBe(true);
    expect(video.playsInline).toBe(true);
    expect(screen.getByRole('heading', { name: 'Miya Sushi' })).toBeTruthy();
});

it('switches to an image and removes video when an image is selected', () => {
    const { container } = render(<Hero media={{ ...media, type: 'image' }} onExploreMenu={vi.fn()} />);
    expect(container.querySelector('video')).toBeNull();
    expect(container.querySelector('.hero').style.backgroundImage).toContain('/images/poster.jpg');
});

it('keeps the fallback image when video loading fails or reduced motion is requested', () => {
    const view = render(<Hero media={media} onExploreMenu={vi.fn()} />);
    fireEvent.error(view.container.querySelector('video'));
    expect(view.container.querySelector('video')).toBeNull();
    expect(view.container.querySelector('.hero').style.backgroundImage).toContain('/images/poster.jpg');
    view.unmount();
    window.matchMedia.mockReturnValue({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() });
    const reduced = render(<Hero media={media} onExploreMenu={vi.fn()} />);
    expect(reduced.container.querySelector('video')).toBeNull();
});

it('lets the administrator publish the saved image without uploading it again', async () => {
    const saved = { ...media, type: 'image' };
    saveHeroMedia.mockResolvedValue(saved);
    const onSaved = vi.fn();
    render(<HeroMediaSettings media={media} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('radio', { name: 'Image' }));
    fireEvent.click(screen.getByRole('button', { name: 'Publier ce fond' }));
    await act(async () => {});
    expect(saveHeroMedia).toHaveBeenCalledWith(expect.objectContaining({ mediaType: 'image', source: 'existing' }), expect.any(Function));
    expect(onSaved).toHaveBeenCalledWith(saved);
});

it('keeps the current media when publication fails and explains the error', async () => {
    saveHeroMedia.mockRejectedValue(new Error('Format vidéo non accepté.'));
    const onSaved = vi.fn();
    render(<HeroMediaSettings media={media} onSaved={onSaved} />);
    fireEvent.click(screen.getByRole('button', { name: 'Publier ce fond' }));
    await act(async () => {});
    expect(screen.getByRole('alert').textContent).toBe('Format vidéo non accepté.');
    expect(onSaved).not.toHaveBeenCalled();
});
