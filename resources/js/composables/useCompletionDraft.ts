import { onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';

/**
 * Offline draft persistence for the appointment completion form (#240, §10b).
 *
 * NARROW SCOPE — this is local draft persistence for the completion form ONLY,
 * not offline-first architecture. There is no offline booking, no offline record
 * browsing, and deliberately NO sync-conflict-resolution engine: the rest of the
 * app assumes connectivity. Rural signal drops mid-entry in a barn or pasture,
 * so the form saves as Jeff types and flushes once when connectivity returns.
 */

/** The minimal storage surface we need — swappable so it is unit-testable. */
export interface DraftStorage {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
}

interface StoredDraft<T> {
    savedAt: string;
    data: T;
}

const memoryStorage = (): DraftStorage => {
    const map = new Map<string, string>();
    return {
        getItem: (k) => (map.has(k) ? (map.get(k) as string) : null),
        setItem: (k, v) => void map.set(k, v),
        removeItem: (k) => void map.delete(k),
    };
};

const defaultStorage = (): DraftStorage => {
    try {
        if (typeof window !== 'undefined' && window.localStorage) {
            return window.localStorage;
        }
    } catch {
        // localStorage can throw in private-mode / sandboxed contexts.
    }
    return memoryStorage();
};

/**
 * Pure, framework-free draft store. Persists a JSON snapshot under a stable key
 * and flushes it to the server EXACTLY ONCE — a re-entrant flush (e.g. two
 * `online` events firing) is guarded so the record is never double-submitted.
 */
export class CompletionDraft<T> {
    private flushing = false;

    constructor(
        private readonly key: string,
        private readonly storage: DraftStorage = defaultStorage(),
    ) {}

    save(data: T): void {
        const payload: StoredDraft<T> = { savedAt: new Date().toISOString(), data };
        this.storage.setItem(this.key, JSON.stringify(payload));
    }

    /** The persisted draft, or null when none exists / the payload is corrupt. */
    load(): T | null {
        const raw = this.storage.getItem(this.key);
        if (raw === null) {
            return null;
        }
        try {
            return (JSON.parse(raw) as StoredDraft<T>).data;
        } catch {
            return null;
        }
    }

    savedAt(): Date | null {
        const raw = this.storage.getItem(this.key);
        if (raw === null) {
            return null;
        }
        try {
            return new Date((JSON.parse(raw) as StoredDraft<T>).savedAt);
        } catch {
            return null;
        }
    }

    hasDraft(): boolean {
        return this.storage.getItem(this.key) !== null;
    }

    clear(): void {
        this.storage.removeItem(this.key);
    }

    /**
     * Submit the persisted draft, then clear it. Returns false when there is
     * nothing to flush or a flush is already in flight — this is what makes the
     * "flush on reconnect exactly once" guarantee hold.
     */
    async flush(submit: (data: T) => Promise<void> | void): Promise<boolean> {
        if (this.flushing || !this.hasDraft()) {
            return false;
        }

        const data = this.load();
        if (data === null) {
            this.clear();
            return false;
        }

        this.flushing = true;
        try {
            await submit(data);
            this.clear();
            return true;
        } finally {
            this.flushing = false;
        }
    }
}

export type DraftStatus = 'idle' | 'saved_local' | 'offline' | 'syncing' | 'synced';

export interface UseCompletionDraftOptions<T> {
    key: string;
    /** The reactive form snapshot to persist; watched deeply. */
    state: Ref<T>;
    /** Submits the draft to the server. Called on reconnect and on demand. */
    onFlush: (data: T) => Promise<void> | void;
    /**
     * Gate for the automatic reconnect flush. Returning false keeps the draft
     * saved-only (Jeff is still mid-entry); it should return true once he has
     * actually tapped "Complete" while offline. Defaults to always-flush.
     */
    shouldFlush?: () => boolean;
    storage?: DraftStorage;
}

/**
 * Vue wrapper: persists `state` locally as it changes, restores it on mount,
 * and flushes once when the browser comes back online. Exposes a clear
 * "saved locally / offline / synced" status for the UI.
 */
export function useCompletionDraft<T extends object>(options: UseCompletionDraftOptions<T>) {
    const draft = new CompletionDraft<T>(options.key, options.storage);
    const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
    const status = ref<DraftStatus>('idle');
    const hasRestored = ref(false);

    const restore = (): T | null => {
        const data = draft.load();
        hasRestored.value = data !== null;
        return data;
    };

    const persist = () => {
        draft.save(options.state.value);
        status.value = isOnline.value ? 'saved_local' : 'offline';
    };

    const flush = async (): Promise<boolean> => {
        if (!draft.hasDraft()) {
            return false;
        }
        status.value = 'syncing';
        const flushed = await draft.flush(options.onFlush);
        status.value = flushed ? 'synced' : status.value;
        return flushed;
    };

    const onOnline = () => {
        isOnline.value = true;
        if (options.shouldFlush === undefined || options.shouldFlush()) {
            void flush();
        } else {
            status.value = draft.hasDraft() ? 'saved_local' : 'idle';
        }
    };
    const onOffline = () => {
        isOnline.value = false;
        status.value = 'offline';
    };

    const stopWatch = watch(options.state, persist, { deep: true });

    onMounted(() => {
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
    });

    onBeforeUnmount(() => {
        stopWatch();
        window.removeEventListener('online', onOnline);
        window.removeEventListener('offline', onOffline);
    });

    return { draft, isOnline, status, hasRestored, restore, persist, flush, clear: () => draft.clear() };
}
