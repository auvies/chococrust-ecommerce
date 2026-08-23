import "@testing-library/jest-dom/vitest";
import { afterEach } from "vitest";
import { cleanup } from "@testing-library/react";

// Vitest doesn't auto-register Testing Library's DOM cleanup the way Jest
// does, so without this, a component left mounted by one test can leak
// into the next test's assertions.
afterEach(() => {
  cleanup();
});
