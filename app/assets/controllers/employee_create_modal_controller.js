import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static values = {
        open: Boolean,
    };

    connect() {
        if (this.openValue) {
            Modal.getOrCreateInstance(this.element).show();
        }
    }
}
