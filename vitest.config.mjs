// JS tests for resources/assets/scripts (npm test). happy-dom stands in for the browser.
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'happy-dom',
    include: ['tests/js/**/*.test.mjs', 'tests/js/**/*.test.tsx'],
  },
});
