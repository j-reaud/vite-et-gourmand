import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'panel', 'toggle'];

    connect() {
        this.timeout = null;
    }

    disconnect() {
        window.clearTimeout(this.timeout);
    }

    submit() {
        this.formTarget.requestSubmit();
    }

    scheduleSubmit() {
        window.clearTimeout(this.timeout);
        this.timeout = window.setTimeout(() => this.submit(), 350);
    }

    toggle() {
        const isOpen = this.panelTarget.classList.toggle('is-open');
        this.toggleTarget.setAttribute('aria-expanded', String(isOpen));
    }
}
