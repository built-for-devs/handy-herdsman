import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Vitest for the narrow set of client-side units that carry business rules —
 * currently the offline completion-draft store (#240). Node environment: the
 * tested logic is pure (storage + JSON), no DOM required.
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
