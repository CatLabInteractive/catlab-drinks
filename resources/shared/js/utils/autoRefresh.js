/**
 * Calls `load` at a fixed interval while the document is visible, and at once
 * when it becomes visible again. Only one load runs at a time: a tick or a
 * manual refresh() during a load does nothing. Errors of `load` are swallowed,
 * because the caller handles its own errors.
 */
export class AutoRefresh {

	/**
	 * @param {() => Promise<any>} load
	 * @param {{ intervalMs: number, doc?: Document, onChange?: (running: boolean) => void }} options
	 */
	constructor(load, { intervalMs, doc = document, onChange = () => {} }) {
		this.load = load;
		this.intervalMs = intervalMs;
		this.doc = doc;
		this.onChange = onChange;

		this.running = false;
		this.stopped = false;
		this.timer = null;

		this.onVisibilityChange = () => {
			if (!this.doc.hidden) {
				this.refresh();
			}
		};
	}

	start() {
		this.stopped = false;
		this.timer = setInterval(() => {
			if (!this.doc.hidden) {
				this.refresh();
			}
		}, this.intervalMs);
		this.doc.addEventListener('visibilitychange', this.onVisibilityChange);

		this.refresh();
	}

	stop() {
		this.stopped = true;
		clearInterval(this.timer);
		this.timer = null;
		this.doc.removeEventListener('visibilitychange', this.onVisibilityChange);
	}

	async refresh() {
		if (this.running || this.stopped) {
			return;
		}

		this.setRunning(true);
		try {
			await this.load();
		} catch (e) {
			// The caller shows its own state for a failed load.
		} finally {
			this.setRunning(false);
		}
	}

	/**
	 * @param {boolean} running
	 */
	setRunning(running) {
		this.running = running;
		this.onChange(running);
	}
}
