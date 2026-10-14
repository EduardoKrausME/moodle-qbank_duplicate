/**
 * Refresh a duplicate question scan while it is being processed.
 *
 * @module qbank_duplicate/scan_refresh
 */

/**
 * Start a single refresh timer for the active report page.
 *
 * @param {number} delay Milliseconds before the next status check.
 */
export const init = (delay = 5000) => {
    window.setTimeout(() => window.location.reload(), delay);
};
