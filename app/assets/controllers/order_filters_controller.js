import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.timeout = null;
    }

    disconnect() {
        window.clearTimeout(this.timeout);
    }

    submit() {
        this.element.requestSubmit();
    }

    scheduleSubmit() {
        window.clearTimeout(this.timeout);
        this.timeout = window.setTimeout(() => this.submit(), 350);
    }
}