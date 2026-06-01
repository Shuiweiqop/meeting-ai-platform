import { useEffect, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { useAudioStore } from '@/stores/audioStore';

function formatTime(s) {
    if (!s || isNaN(s)) return '0:00';
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return `${m}:${String(sec).padStart(2, '0')}`;
}

export default function GlobalAudioPlayer() {
    const audioRef = useRef(null);

    const {
        meetingTitle, meetingHref, audioUrl,
        currentTime, duration, isPlaying,
        setCurrentTime, setDuration, setIsPlaying,
        registerSeek, close, togglePlay,
    } = useAudioStore();

    // Register the imperative seek function so AudioTranscriptSync can call it
    useEffect(() => {
        registerSeek((time) => {
            if (!audioRef.current) return;
            audioRef.current.currentTime = time;
        });
    }, []);

    // Sync isPlaying → audio element
    useEffect(() => {
        if (!audioRef.current || !audioUrl) return;
        if (isPlaying) {
            audioRef.current.play().catch(() => setIsPlaying(false));
        } else {
            audioRef.current.pause();
        }
    }, [isPlaying, audioUrl]);

    // Reset audio element when audioUrl changes
    useEffect(() => {
        if (!audioRef.current) return;
        audioRef.current.load();
        if (isPlaying) audioRef.current.play().catch(() => {});
    }, [audioUrl]);

    const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

    const seekByClick = (e) => {
        if (!audioRef.current || !duration) return;
        const rect = e.currentTarget.getBoundingClientRect();
        const ratio = (e.clientX - rect.left) / rect.width;
        const newTime = ratio * duration;
        audioRef.current.currentTime = newTime;
        setCurrentTime(newTime);
    };

    if (!audioUrl) return null;

    return (
        <>
            {/* Hidden audio element — the single source of truth */}
            <audio
                ref={audioRef}
                src={audioUrl}
                onTimeUpdate={(e) => setCurrentTime(e.target.currentTime)}
                onLoadedMetadata={(e) => setDuration(e.target.duration)}
                onPlay={() => setIsPlaying(true)}
                onPause={() => setIsPlaying(false)}
                onEnded={() => setIsPlaying(false)}
            />

            {/* Mini player bar */}
            <div className="fixed bottom-0 left-0 right-0 z-50 border-t border-gray-200 bg-white/95 backdrop-blur-sm shadow-lg">
                {/* Progress bar — clickable */}
                <div
                    className="h-1 w-full cursor-pointer bg-gray-200 hover:bg-gray-300 transition-colors"
                    onClick={seekByClick}
                >
                    <div
                        className="h-full bg-indigo-500 transition-all duration-100"
                        style={{ width: `${progress}%` }}
                    />
                </div>

                <div className="mx-auto flex max-w-7xl items-center gap-4 px-4 py-2.5 sm:px-6 lg:px-8">
                    {/* Meeting title — links back */}
                    <div className="flex min-w-0 flex-1 items-center gap-2">
                        <svg className="h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                        </svg>
                        {meetingHref ? (
                            <Link href={meetingHref} className="truncate text-sm font-medium text-gray-800 hover:text-indigo-600">
                                {meetingTitle}
                            </Link>
                        ) : (
                            <span className="truncate text-sm font-medium text-gray-800">{meetingTitle}</span>
                        )}
                    </div>

                    {/* Time */}
                    <span className="shrink-0 text-xs tabular-nums text-gray-500">
                        {formatTime(currentTime)} / {formatTime(duration)}
                    </span>

                    {/* Play / Pause */}
                    <button
                        onClick={togglePlay}
                        className="shrink-0 rounded-full bg-indigo-600 p-2 text-white hover:bg-indigo-500 transition-colors"
                        aria-label={isPlaying ? 'Pause' : 'Play'}
                    >
                        {isPlaying ? (
                            <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                            </svg>
                        ) : (
                            <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        )}
                    </button>

                    {/* Close */}
                    <button
                        onClick={close}
                        className="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                        aria-label="Close player"
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {/* Bottom padding so page content isn't hidden behind the player */}
            <div className="h-14" />
        </>
    );
}
