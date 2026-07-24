import { describe, expect, it, vi } from 'vitest';
import { CompletionDraft, type DraftStorage } from './useCompletionDraft';

/**
 * #240 offline draft persistence (§10b). Two mandatory behaviors:
 *  1. the draft is restored after a simulated reload with no network, and
 *  2. it flushes to the server on reconnect EXACTLY ONCE.
 * The store is exercised directly against an in-memory storage that survives
 * across instances — the stand-in for a page reload with localStorage intact.
 */

const makeStorage = (): DraftStorage => {
    const map = new Map<string, string>();
    return {
        getItem: (k) => (map.has(k) ? (map.get(k) as string) : null),
        setItem: (k, v) => void map.set(k, v),
        removeItem: (k) => void map.delete(k),
    };
};

interface Form {
    completed_at: string;
    mileage: number;
    notes: string;
}

const sample: Form = { completed_at: '2026-07-24T09:00:00Z', mileage: 42, notes: 'Bred cow #7' };

describe('CompletionDraft', () => {
    it('restores the draft after a simulated reload with no network', () => {
        const storage = makeStorage();

        // Jeff types with no connectivity — the draft is saved locally.
        new CompletionDraft<Form>('visit-completion-1', storage).save(sample);

        // Page reloads (still offline): a fresh instance reads the same storage.
        const restored = new CompletionDraft<Form>('visit-completion-1', storage).load();

        expect(restored).toEqual(sample);
    });

    it('returns null when there is no draft', () => {
        expect(new CompletionDraft<Form>('missing', makeStorage()).load()).toBeNull();
    });

    it('flushes to the server on reconnect exactly once', async () => {
        const storage = makeStorage();
        const draft = new CompletionDraft<Form>('visit-completion-1', storage);
        draft.save(sample);

        const submit = vi.fn().mockResolvedValue(undefined);

        // Reconnect may fire more than one `online` event — flush must be idempotent.
        const first = await draft.flush(submit);
        const second = await draft.flush(submit);

        expect(first).toBe(true);
        expect(second).toBe(false);
        expect(submit).toHaveBeenCalledTimes(1);
        expect(submit).toHaveBeenCalledWith(sample);
        expect(draft.hasDraft()).toBe(false);
    });

    it('does not double-submit when two flushes race', async () => {
        const storage = makeStorage();
        const draft = new CompletionDraft<Form>('visit-completion-1', storage);
        draft.save(sample);

        let resolve!: () => void;
        const submit = vi.fn().mockReturnValue(new Promise<void>((r) => (resolve = r)));

        const a = draft.flush(submit);
        const b = draft.flush(submit); // in-flight guard rejects this one
        resolve();
        const [ra, rb] = await Promise.all([a, b]);

        expect([ra, rb].filter(Boolean)).toHaveLength(1);
        expect(submit).toHaveBeenCalledTimes(1);
    });

    it('keeps the draft when the server submit fails, so nothing is lost', async () => {
        const storage = makeStorage();
        const draft = new CompletionDraft<Form>('visit-completion-1', storage);
        draft.save(sample);

        const submit = vi.fn().mockRejectedValue(new Error('offline again'));

        await expect(draft.flush(submit)).rejects.toThrow('offline again');
        expect(draft.hasDraft()).toBe(true); // retry still possible
    });
});
