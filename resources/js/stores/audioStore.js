import { create } from 'zustand';

/**
 * Global audio player store.
 *
 * The <audio> DOM element lives in GlobalAudioPlayer (always mounted in
 * AuthenticatedLayout). AudioTranscriptSync reads currentTime + isPlaying,
 * and calls seekTo() / setIsPlaying() to control playback from any page.
 */
export const useAudioStore = create((set) => ({
    // Meeting context — shown in mini player
    meetingId:    null,
    meetingTitle: null,
    meetingHref:  null,

    // Audio state
    audioUrl:    null,
    currentTime: 0,
    duration:    0,
    isPlaying:   false,

    // Registered by GlobalAudioPlayer on mount so any component can seek
    seekTo: (_time) => {},

    // ── Actions ──────────────────────────────────────────────────────────────

    /** Load a new meeting into the global player. */
    load: ({ meetingId, meetingTitle, meetingHref, audioUrl }) =>
        set({ meetingId, meetingTitle, meetingHref, audioUrl, currentTime: 0, isPlaying: false }),

    setCurrentTime: (currentTime) => set({ currentTime }),
    setDuration:    (duration)    => set({ duration }),
    setIsPlaying:   (isPlaying)   => set({ isPlaying }),
    togglePlay:     ()            => set((s) => ({ isPlaying: !s.isPlaying })),

    /** Registered by GlobalAudioPlayer so AudioTranscriptSync can seek imperatively. */
    registerSeek: (fn) => set({ seekTo: fn }),

    /** Close / eject the player. */
    close: () => set({
        meetingId: null, meetingTitle: null, meetingHref: null,
        audioUrl: null, currentTime: 0, duration: 0, isPlaying: false,
    }),
}));
